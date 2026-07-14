<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Media_Replacer
 * 
 * Handles the logic of replacing a media file while keeping the same attachment ID.
 */
class Media_Replacer
{
    /**
     * Replace a media attachment with a new file.
     *
     * @param int    $attachment_id The ID of the attachment to replace.
     * @param array  $file_data     The $_FILES entry for the new file.
     * @param array  $options       Optional configuration:
     *                              - 'replace_type': 'replace' (keep name) or 'replace_and_search' (new name)
     *                              - 'timestamp_replace': 'keep', 'current', or 'custom'
     *                              - 'custom_date': Date string for 'custom' mode
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function replace($attachment_id, $file_data, $options = [])
    {
        global $wpdb;

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new \WP_Error('invalid_attachment', __('Invalid attachment ID.', 'mediamatic'));
        }

        if (!current_user_can('edit_post', $attachment_id)) {
            return new \WP_Error('forbidden', __('You do not have permission to edit this attachment.', 'mediamatic'));
        }

        $options = wp_parse_args($options, [
            'replace_type' => 'replace',
            'timestamp_replace' => 'keep',
            'custom_date' => ''
        ]);

        $old_path = get_attached_file($attachment_id);
        $old_url = wp_get_attachment_url($attachment_id);
        $old_meta = wp_get_attachment_metadata($attachment_id);

        // 1. Handle the upload
        $overrides = [
            'test_form' => false,
            'action' => 'mediamatic_replace_media'
        ];

        // Ensure WordPress admin functions are available
        require_once ABSPATH . 'wp-admin/includes/file.php';

        // Control upload directory and handle filename pre-filtering
        $upload_dir_filter = function ($dirs) use ($old_path) {
            if ($old_path) {
                $old_dir = dirname($old_path);
                $base_dir = $dirs['basedir'];

                if (strpos($old_dir, $base_dir) === 0) {
                    $relative_path = ltrim(substr($old_dir, strlen($base_dir)), DIRECTORY_SEPARATOR);
                    $dirs['path'] = $old_dir;
                    $dirs['url'] = $dirs['baseurl'] . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
                    $dirs['subdir'] = '/' . $relative_path;
                }
            }
            return $dirs;
        };
        add_filter('upload_dir', $upload_dir_filter, 20);

        $upload = wp_handle_upload($file_data, $overrides);
        remove_filter('upload_dir', $upload_dir_filter, 20);

        if (isset($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        $new_path = $upload['file'];
        $new_url = $upload['url'];
        $new_type = $upload['type'];

        // 2. Filename Preservation (Exact name check)
        if ($options['replace_type'] === 'replace' && $old_path) {
            $expected_path = $old_path;

            // If WordPress added a suffix during upload because the file existed
            if ($new_path !== $expected_path) {
                // Delete the OLD file (and thumbnails) now so we can rename the NEW one to the OLD name
                self::delete_attachment_files($attachment_id, true, $new_path);

                // Use WP_Filesystem to move the file
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Using WP_Filesystem move() via wrapper.
                if ($wp_filesystem->move($new_path, $expected_path, true)) {
                    $new_path = $expected_path;
                    $new_url = $old_url; // URL stays exactly the same
                }
            } else {
                // Same path? WordPress overwrote it or it was clean. Still need to delete thumbnails.
                self::delete_attachment_files($attachment_id, false);
            }
        } else {
            // Rename/New Name: Clean up old files before proceeding
            self::delete_attachment_files($attachment_id, true, $new_path);
        }

        // 3. Update the attachment
        update_attached_file($attachment_id, $new_path);

        $update_data = [
            'ID' => $attachment_id,
            'post_mime_type' => $new_type,
        ];

        if ($options['replace_type'] === 'replace_and_search') {
            $update_data['guid'] = $new_url;
            $file_info = pathinfo($new_path);
            $update_data['post_title'] = $file_info['filename'];
            $update_data['post_name'] = sanitize_title($file_info['filename']);

            // TRIGGER SEARCH AND REPLACE
            self::update_database_references($old_url, $new_url, $old_meta);
        }

        // 4. Handle Date updates
        if ($options['timestamp_replace'] === 'current') {
            $update_data['post_date'] = current_time('mysql');
            $update_data['post_date_gmt'] = current_time('mysql', 1);
        } elseif ($options['timestamp_replace'] === 'custom' && !empty($options['custom_date'])) {
            $update_data['post_date'] = gmdate('Y-m-d H:i:s', strtotime($options['custom_date']));
            $update_data['post_date_gmt'] = get_gmt_from_date($update_data['post_date']);
        }

        wp_update_post($update_data);

        // 5. Regenerate metadata and thumbnails
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $new_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return true;
    }

    /**
     * Update all database references from old URL to new URL.
     */
    private static function update_database_references($old_url, $new_url, $old_meta)
    {
        global $wpdb;

        // 1. Basic URL replacement
        $queries = [
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key != '_wp_attached_file' AND meta_key != '_wp_attachment_metadata'"
        ];

        foreach ($queries as $query) {
            $wpdb->query($wpdb->prepare($query, $old_url, $new_url)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        }

        // 2. Thumbnail URLs replacement
        if (!empty($old_meta['sizes'])) {
            $old_base_url = dirname($old_url);
            $new_base_url = dirname($new_url);

            $old_info = pathinfo($old_url);
            $new_info = pathinfo($new_url);

            $old_base_name = $old_info['filename'];
            $new_base_name = $new_info['filename'];

            $old_ext = isset($old_info['extension']) ? '.' . $old_info['extension'] : '';
            $new_ext = isset($new_info['extension']) ? '.' . $new_info['extension'] : '';

            foreach ($old_meta['sizes'] as $size => $info) {
                // Old thumbnail URL
                $old_thumb_url = $old_base_url . '/' . $info['file'];

                // Try to construct the new thumbnail URL
                // The thumbnail file name is usually: base-name + '-' + WIDTH + 'x' + HEIGHT + '.' + ext
                // But it's safer to just replace the base name and extension parts in the old thumbnail filename
                $new_thumb_filename = $info['file'];

                // Replace base name
                if ($old_base_name !== $new_base_name) {
                    $new_thumb_filename = preg_replace('/^' . preg_quote($old_base_name, '/') . '/', $new_base_name, $new_thumb_filename);
                }

                // Replace extension
                if ($old_ext !== $new_ext) {
                    $new_thumb_filename = preg_replace('/' . preg_quote($old_ext, '/') . '$/', $new_ext, $new_thumb_filename);
                }

                $new_thumb_url = $new_base_url . '/' . $new_thumb_filename;

                if ($old_thumb_url !== $new_thumb_url) {
                    foreach ($queries as $query) {
                        $wpdb->query($wpdb->prepare($query, $old_thumb_url, $new_thumb_url)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
                    }
                }
            }
        }
    }

    /**
     * Delete files associated with an attachment without deleting the attachment itself.
     * 
     * @param int    $attachment_id     The attachment ID.
     * @param bool   $delete_main_file  Whether to delete the main attached file.
     * @param string $exclude_path     An optional path to exclude from deletion (e.g. if it matches the new file).
     */
    private static function delete_attachment_files($attachment_id, $delete_main_file = true, $exclude_path = '')
    {
        $file = get_attached_file($attachment_id);
        $meta = wp_get_attachment_metadata($attachment_id);
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);

        if (!empty($meta['sizes'])) {
            $path = dirname($file);
            foreach ($meta['sizes'] as $size => $size_info) {
                $intermediate_file = $path . '/' . $size_info['file'];
                if (file_exists($intermediate_file) && $intermediate_file !== $exclude_path) {
                    wp_delete_file($intermediate_file);
                }
            }
        }

        if (!empty($backup_sizes)) {
            $path = dirname($file);
            foreach ($backup_sizes as $size => $size_info) {
                $backup_file = $path . '/' . $size_info['file'];
                if (file_exists($backup_file) && $backup_file !== $exclude_path) {
                    wp_delete_file($backup_file);
                }
            }
        }

        if ($delete_main_file && file_exists($file) && $file !== $exclude_path) {
            wp_delete_file($file);
        }
    }
}
