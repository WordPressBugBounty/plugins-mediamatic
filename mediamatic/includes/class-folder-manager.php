<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Folder_Manager
 *
 * Core CRUD operations for folders.
 */
class Folder_Manager
{

    public function __construct()
    {
        add_action('add_attachment', [$this, 'handle_new_attachment']);
        add_action('deleted_user', [$this, 'handle_deleted_user'], 10, 2);
    }

    /**
     * Get table names.
     */
    private static function get_tables()
    {
        global $wpdb;
        return [
            'folders' => $wpdb->prefix . 'mediamatic_folders',
            'relationships' => $wpdb->prefix . 'mediamatic_relationships',
        ];
    }

    public function get_folders($args = [])
    {
        global $wpdb;
        $tables = self::get_tables();

        $order_by = 'order_index ASC, id ASC';
        if (isset($args['orderby']) && wp_unslash($args['orderby']) === 'name') {
            $order_by = 'name ASC';
        }

        $post_type = isset($args['post_type']) ? sanitize_key($args['post_type']) : 'attachment';

        $where_clauses = [];
        // Global folders have user_id = 0 or NULL (legacy)
        $where_clauses[] = "(user_id = 0 OR user_id IS NULL)";

        $where_clauses[] = $wpdb->prepare('post_type = %s', $post_type);

        $where = $where_clauses ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

        $sql = "SELECT * FROM {$tables['folders']}{$where} ORDER BY $order_by";

        // Generate versioned cache key (include user_id + post_type so cache is scoped correctly)
        $cache_version = get_option('mediamatic_db_version_hash', '1');
        $current_uid = get_current_user_id();
        $cache_key = 'mediamatic_all_folders_' . md5(serialize($args) . $cache_version . $current_uid . $post_type);
        $folders = wp_cache_get($cache_key, 'mediamatic');

        if (false === $folders) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            $folders = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
            if ($folders) {
                foreach ($folders as &$folder) {
                    $folder['id'] = (int) $folder['id'];
                    $folder['parent_id'] = (int) $folder['parent_id'];
                    $folder['order_index'] = (int) $folder['order_index'];
                }
            } else {
                $folders = [];
            }
            wp_cache_set($cache_key, $folders, 'mediamatic', 0);
        }

        return $folders;
    }

    /**
     * Get folder by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function get_folder($id)
    {
        global $wpdb;
        $tables = self::get_tables();

        $sql = $wpdb->prepare("SELECT * FROM {$tables['folders']} WHERE id = %d", $id); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $folder = $wpdb->get_row($sql, ARRAY_A); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

        return $folder ? $folder : null;
    }

    /**
     * Create a folder.
     *
     * @param array $data
     * @return int|\WP_Error
     */
    public function create_folder($data)
    {
        if (!current_user_can('mediamatic_create_folder')) {
            return new \WP_Error('rest_forbidden', __('Sorry, you are not allowed to create folders.', 'mediamatic'));
        }

        global $wpdb;
        $tables = self::get_tables();

        $name = isset($data['name']) ? mediamatic_sanitize_folder_name($data['name']) : __('New Folder', 'mediamatic');
        $parent_id = isset($data['parent_id']) ? absint($data['parent_id']) : 0;

        // Force root level for Free version
        $parent_id = 0;

        $color = isset($data['color']) ? sanitize_hex_color($data['color']) : '';
        $post_type = isset($data['post_type']) ? sanitize_key($data['post_type']) : 'attachment';

        if (empty($name)) {
            return new \WP_Error('empty_name', __('Folder name cannot be empty.', 'mediamatic'));
        }

        // Get max order.
        $max_order = $wpdb->get_var($wpdb->prepare("SELECT MAX(order_index) FROM {$tables['folders']} WHERE parent_id = %d", $parent_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $order = intval($max_order) + 1;

        $user_id = 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $tables['folders'],
            [
                'user_id' => $user_id,
                'post_type' => $post_type,
                'name' => $name,
                'parent_id' => $parent_id,
                'color' => $color,
                'order_index' => $order,
            ]
        );

        if ($inserted) {
            $this->invalidate_cache();
            return $wpdb->insert_id;
        }

        return new \WP_Error('insert_failed', __('Could not insert folder.', 'mediamatic'));
    }

    /**
     * Update a folder.
     *
     * @param int   $id
     * @param array $data
     * @return bool|\WP_Error
     */
    public function update_folder($id, $data)
    {
        if (!Permissions::user_can_edit_folder($id)) {
            return new \WP_Error('rest_forbidden', __('Sorry, you are not allowed to edit this folder.', 'mediamatic'));
        }

        global $wpdb;
        $tables = self::get_tables();
        $id = absint($id);

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $name = mediamatic_sanitize_folder_name($data['name']);
            if (empty($name)) {
                return new \WP_Error('empty_name', __('Folder name cannot be empty.', 'mediamatic'));
            }
            $update_data['name'] = $name;
            $format[] = '%s';
        }

        if (isset($data['parent_id'])) {
            $parent_id = absint($data['parent_id']);

            // Force root level for Free version
            $parent_id = 0;

            // Prevent assigning parent to itself
            if ($parent_id === $id) {
                return new \WP_Error('invalid_parent', __('Folder cannot be its own parent.', 'mediamatic'));
            }
            $update_data['parent_id'] = $parent_id;
            $format[] = '%d';
        }

        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
            $format[] = '%s';
        }

        if (isset($data['order_index'])) {
            $update_data['order_index'] = intval($data['order_index']);
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return true; // Nothing to update
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $tables['folders'],
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if (false !== $updated) {
            $this->invalidate_cache();
            return true;
        }

        return new \WP_Error('update_failed', __('Could not update folder.', 'mediamatic'));
    }

    /**
     * Reorder folder and its siblings gracefully within a new/same parent.
     *
     * @param int $folder_id
     * @param int $target_parent_id
     * @param int $target_index
     * @return bool|\WP_Error
     */
    public function reorder_folder($folder_id, $target_parent_id, $target_index)
    {
        if (!Permissions::user_can_edit_folder($folder_id)) {
            return new \WP_Error('rest_forbidden', __('Sorry, you are not allowed to move this folder.', 'mediamatic'));
        }

        global $wpdb;
        $tables = self::get_tables();

        $folder_id = absint($folder_id);
        $target_parent_id = absint($target_parent_id);
        $target_index = absint($target_index);

        // Force root level for Free version
        $target_parent_id = 0;

        if ($folder_id === $target_parent_id) {
            return new \WP_Error('invalid_parent', __('Folder cannot be its own parent.', 'mediamatic'));
        }

        // Prevent moving a parent into its own child (circular reference check)
        $current_parent_check = $target_parent_id;
        while ($current_parent_check > 0) {
            if ($current_parent_check === $folder_id) {
                return new \WP_Error('circular_reference', __('Cannot move a folder into its own subfolder.', 'mediamatic'));
            }
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $parent_row = $wpdb->get_row($wpdb->prepare("SELECT parent_id FROM {$tables['folders']} WHERE id = %d", $current_parent_check), ARRAY_A); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$parent_row)
                break;
            $current_parent_check = (int) $parent_row['parent_id'];
        }

        // We need the post_type and user_id to ensure we only order siblings in the same scope
        $original_folder = $this->get_folder($folder_id);
        if (!$original_folder) {
            return new \WP_Error('not_found', __('Folder not found.', 'mediamatic'));
        }

        // Get all children of target parent EXCEPT the one being moved, scoped by user_id and post_type
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $children = $wpdb->get_results($wpdb->prepare( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT id FROM {$tables['folders']} WHERE parent_id = %d AND id != %d AND user_id = %d AND post_type = %s ORDER BY order_index ASC, id ASC",
            $target_parent_id,
            $folder_id,
            $original_folder['user_id'],
            $original_folder['post_type']
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $children_ids = array_column($children, 'id');

        // Splice the folder into the requested position
        array_splice($children_ids, $target_index, 0, [$folder_id]);

        // Loop array and bulk update the indices and parent
        foreach ($children_ids as $index => $id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $tables['folders'],
                ['parent_id' => $target_parent_id, 'order_index' => $index],
                ['id' => $id],
                ['%d', '%d'],
                ['%d']
            );
        }

        $this->invalidate_cache();
        return true;
    }



    /**
     * Delete a folder.
     *
     * @param int  $id
     * @param bool $recursive Whether to delete children or move them to top.
     * @return bool|\WP_Error
     */
    public function delete_folder($id, $recursive = true)
    {
        if (!Permissions::user_can_delete_folder($id)) {
            return new \WP_Error('rest_forbidden', __('Sorry, you are not allowed to delete this folder.', 'mediamatic'));
        }

        global $wpdb;
        $tables = self::get_tables();
        $id = absint($id);

        // We need to re-assign children if not recursive, or delete them if recursive.
        $children_sql = $wpdb->prepare("SELECT id FROM {$tables['folders']} WHERE parent_id = %d", $id); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $children = $wpdb->get_col($children_sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!empty($children)) {
            if ($recursive) {
                foreach ($children as $child_id) {
                    $this->delete_folder($child_id, true);
                }
            } else {
                // Move children to root
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $tables['folders'],
                    ['parent_id' => 0],
                    ['parent_id' => $id],
                    ['%d'],
                    ['%d']
                );
            }
        }

        // Delete associations
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($tables['relationships'], ['folder_id' => $id], ['%d']);

        // Delete folder
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($tables['folders'], ['id' => $id], ['%d']);

        if ($deleted) {
            $this->invalidate_cache();
            return true;
        }

        return new \WP_Error('delete_failed', __('Could not delete folder.', 'mediamatic'));
    }

    /**
     * Set attachments folders.
     *
     * @param int|array $attachment_ids
     * @param int|array $folder_ids
     * @param bool      $append
     * @return bool
     */
    public function set_attachments_folders($attachment_ids, $folder_ids, $append = false)
    {
        global $wpdb;
        $tables = self::get_tables();

        $attachment_ids = (array) $attachment_ids;
        $attachment_ids = array_map('absint', $attachment_ids);

        $folder_ids = (array) $folder_ids;
        $folder_ids = array_map('absint', $folder_ids);

        if (empty($attachment_ids)) {
            return false;
        }

        $this->invalidate_cache();

        foreach ($attachment_ids as $attachment_id) {
            if (!$append) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->delete($tables['relationships'], ['object_id' => $attachment_id], ['%d']);
            }
            foreach ($folder_ids as $folder_id) {
                // We don't insert 0 folder id, 0 is 'Uncategorized' typically logic-wise.
                if ($folder_id > 0) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->replace(
                        $tables['relationships'],
                        [
                            'object_id' => $attachment_id,
                            'folder_id' => $folder_id,
                        ],
                        ['%d', '%d']
                    );
                }
            }
        }

        return true;
    }

    /**
     * Get attachments count per folder.
     *
     * When 'folder_counter' setting is 'recursive', each folder's count
     * includes all files in its direct and descendant sub-folders.
     *
     * @return array [ folder_id => count, 'total' => total_count ]
     */
    public function get_folder_counts($post_type = 'attachment')
    {
        global $wpdb;
        $tables = self::get_tables();
        $settings = get_option('mediamatic_settings', []);
        $mode = ($settings['folder_counter'] ?? 'direct') === 'recursive' ? 'recursive' : 'direct';
        $post_type = sanitize_key($post_type ?: 'attachment');

        $is_attachment = ($post_type === 'attachment');
        $current_user_id = get_current_user_id();
        $hash = get_option('mediamatic_db_version_hash', '1');

        $settings = get_option('mediamatic_settings', []);
        $folder_mode = $settings['mediamatic_folder_mode'] ?? 'global';

        $cache_key = 'mediamatic_folder_counts_v3_' . $mode . '_' . $folder_mode . '_' . $post_type . '_' . $current_user_id . '_' . $hash;
        $counts = wp_cache_get($cache_key, 'mediamatic');

        if (false === $counts) {
            $folder_user_join = " INNER JOIN {$tables['folders']} f ON r.folder_id = f.id";
            $folder_user_sql = '';

            // Global folders have user_id = 0 or NULL (legacy)
            $folder_user_sql = ' AND (f.user_id = 0 OR f.user_id IS NULL)';

            $status_sql = $is_attachment ? "p.post_status != 'trash'" : "p.post_status NOT IN ('trash', 'auto-draft', 'inherit')";
            // $folder_user_join, $folder_user_sql and $status_sql are built internally from wpdb->prepare() or safe literals.
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare(
                "SELECT r.folder_id, COUNT(r.object_id) as count
                 FROM {$tables['relationships']} r
                 INNER JOIN {$wpdb->posts} p ON r.object_id = p.ID" .
                $folder_user_join . "
                 WHERE p.post_type = %s AND {$status_sql}
                   AND r.folder_id > 0" . $folder_user_sql . "
                 GROUP BY r.folder_id",
                $post_type
            ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $results = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $direct = [];
            foreach ((array) $results as $row) {
                $direct[(int) $row['folder_id']] = (int) $row['count'];
            }

            // Total: We count what's in the DB globally because WP grid shows all media.
            $status_sql = $is_attachment ? "post_status != 'trash'" : "post_status NOT IN ('trash', 'auto-draft', 'inherit')";
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var($wpdb->prepare( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND {$status_sql}",
                $post_type
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($mode === 'recursive') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $all_folders = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->prepare(
                        "SELECT id, parent_id FROM {$tables['folders']} WHERE post_type = %s",
                        $post_type
                    ),
                    ARRAY_A
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

                $children = [];
                foreach ((array) $all_folders as $f) {
                    $children[(int) $f['parent_id']][] = (int) $f['id'];
                }

                $recursive_helper = function (int $folder_id) use (&$recursive_helper, $children, $direct): int {
                    $t = $direct[$folder_id] ?? 0;
                    foreach ($children[$folder_id] ?? [] as $child_id) {
                        $t += $recursive_helper($child_id);
                    }
                    return $t;
                };

                $counts = [
                    'total' => $total,
                    'categorized' => array_sum($direct)
                ];
                foreach ((array) $all_folders as $f) {
                    $fid = (int) $f['id'];
                    $counts[$fid] = $recursive_helper($fid);
                }
            } else {
                $counts = [
                    'total' => $total,
                    'categorized' => array_sum($direct)
                ];
                foreach ($direct as $fid => $cnt) {
                    $counts[$fid] = $cnt;
                }
            }

            wp_cache_set($cache_key, $counts, 'mediamatic', 120);
        }

        return $counts;
    }

    public function invalidate_cache()
    {
        // Bump version option to effectively discard all older versioned cache keys.
        update_option('mediamatic_db_version_hash', time());
        wp_cache_delete('mediamatic_folder_counts', 'mediamatic');
    }

    /**
     * Auto-assign uploaded media to a folder if 'mediamatic_folder' is passed in the request.
     * 
     * @param int $post_id The newly uploaded attachment ID.
     */
    public function handle_new_attachment($post_id)
    {
        if (
            !isset($_REQUEST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'mediamatic_nonce')
        ) {
            return;
        }

        if (!current_user_can('upload_files')) {
            return;
        }

        if (isset($_REQUEST['mediamatic_folder'])) {
            $folder_id = absint(wp_unslash($_REQUEST['mediamatic_folder']));

            if ($folder_id > 0) {
                if (!Permissions::user_can_edit_folder($folder_id)) {
                    return;
                }

                // Assign the new attachment to this folder
                $this->set_attachments_folders([$post_id], [$folder_id]);

                // Clear the counts cache so the UI updates immediately
                $this->invalidate_cache();
            }
        }
    }

    public function handle_deleted_user($id, $reassign = null)
    {
        global $wpdb;
        $tables = self::get_tables();

        // Reassign to target user, or fallback to an administrator
        $new_owner = $reassign ? (int) $reassign : $this->get_default_admin_id();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $tables['folders'],
            ['user_id' => $new_owner],
            ['user_id' => $id],
            ['%d'],
            ['%d']
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $tables['folders'],
            ['user_id' => $new_owner],
            ['user_id' => $id],
            ['%d'],
            ['%d']
        );

        $this->invalidate_cache();
    }

    private function get_default_admin_id()
    {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
        return !empty($admins) ? $admins[0]->ID : 1;
    }
}
