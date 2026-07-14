<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Assets
 *
 * Enqueues scripts and styles for the backend.
 */
class Assets
{

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_media', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue scripts in admin.
     *
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook = '')
    {
        $mediamatic_settings = get_option('mediamatic_settings', []);
        $allowed_post_types = !empty($mediamatic_settings['allowed_post_types']) ? (array) $mediamatic_settings['allowed_post_types'] : [];

        // Determine context
        $on_media_library = ('upload.php' === $hook);
        $on_post_list = ('edit.php' === $hook);
        $on_post_edit = in_array($hook, ['post.php', 'post-new.php', 'widgets.php', 'customize.php'], true);
        $is_wp_enqueue_media = doing_action('wp_enqueue_media');

        $current_post_type = 'attachment';

        if ($on_post_list) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        }

        // Load only on media library OR allowed CPT list pages, or when media modal is enqueued
        $should_load = $on_media_library
            || ($on_post_list && !empty($allowed_post_types) && in_array($current_post_type, $allowed_post_types, true))
            || $on_post_edit
            || $is_wp_enqueue_media;

        if (!$should_load) {
            return;
        }

        if (!Permissions::can_view_folders()) {
            return;
        }

        $asset_file = MEDIAMATIC_DIR . 'build/index.asset.php';
        $dependencies = [];
        $version = MEDIAMATIC_VERSION;

        if (file_exists($asset_file)) {
            $asset = require $asset_file;
            $dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : [];
            $version = isset($asset['version']) ? $asset['version'] : MEDIAMATIC_VERSION;
        }

        wp_enqueue_style(
            'mediamatic-admin-css',
            MEDIAMATIC_URL . 'build/style-index.css',
            [],
            $version
        );

        $dependencies[] = 'jquery-ui-draggable';
        $dependencies[] = 'jquery-ui-droppable';

        wp_enqueue_script(
            'mediamatic-admin-js',
            MEDIAMATIC_URL . 'build/index.js',
            $dependencies,
            $version,
            true
        );


        $current_folder_id = -1;
        if (isset($_GET['mediamatic_folder'])) { // phpcs:ignore
            $current_folder_id = intval(wp_unslash($_GET['mediamatic_folder'])); // phpcs:ignore
        }

        wp_localize_script(
            'mediamatic-admin-js',
            'mediamaticParams',
            [
                'restUrl' => esc_url_raw(rest_url('mediamatic/v1/')),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxNonce' => wp_create_nonce('mediamatic_ajax'),
                'currentFolderId' => $current_folder_id,
                'showBreadcrumb' => 0,
                'perUserFolders' => 0,
                'folderCounter' => ($mediamatic_settings['folder_counter'] ?? 'direct') === 'recursive' ? 'recursive' : 'direct',
                'allowedPostTypes' => array_values($allowed_post_types),
                'postType' => $current_post_type,
                'settingsUrl' => esc_url(admin_url('admin.php?page=mediamatic')),
                'canCreate' => current_user_can('mediamatic_create_folder'),
                'canEdit' => current_user_can('mediamatic_edit_folder'),
                'canDelete' => current_user_can('mediamatic_delete_folder'),
            ]
        );
    }
}
