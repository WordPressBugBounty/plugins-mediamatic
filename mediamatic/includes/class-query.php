<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Query
 *
 * Hooks into WordPress queries to filter attachments by our custom folders.
 */
class Query
{

    public function __construct()
    {
        // Intercept AJAX Grid requests (wp.media backbone)
        add_filter('ajax_query_attachments_args', [$this, 'filter_ajax_attachments'], 10, 1);

        // Intercept List View requests and modify SQL clauses to do the JOIN
        add_filter('posts_clauses', [$this, 'filter_attachments_clauses'], 10, 2);
    }

    /**
     * Pass the requested folder ID into the WP_Query vars during AJAX requests.
     */
    public function filter_ajax_attachments($query)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query filter, no state change.
        if (isset($_REQUEST['mediamatic_folder'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $query['mediamatic_folder'] = intval(wp_unslash($_REQUEST['mediamatic_folder']));
        }
        return $query;
    }

    /**
     * Modify SQL clauses to INNER JOIN / LEFT JOIN our custom relationship table based on folder ID.
     */
    public function filter_attachments_clauses($clauses, $wp_query)
    {
        global $wpdb;

        // Ensure we are only touching attachment queries
        $post_type = $wp_query->get('post_type');
        if ($post_type !== 'attachment' && $post_type !== 'any') {
            return $clauses;
        }

        $folder_id = false;

        // 1. Try getting from Backbone AJAX Grid request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filtering, sanitized later.
        if (wp_doing_ajax() && isset($_REQUEST['action']) && wp_unslash($_REQUEST['action']) === 'query-attachments') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (isset($_REQUEST['query']['mediamatic_folder'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $folder_id = wp_unslash($_REQUEST['query']['mediamatic_folder']);
            }
        }
        // 2. Try getting from WP_Query explicitly appended vars
        elseif (isset($wp_query->query_vars['mediamatic_folder'])) {
            $folder_id = $wp_query->query_vars['mediamatic_folder'];
        }
        // 3. Try getting from standard GET (List View)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filtering.
        elseif (isset($_GET['mediamatic_folder']) && is_admin() && (!isset($_GET['page']) || wp_unslash($_GET['page']) != 'mediamatic')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $folder_id = wp_unslash($_GET['mediamatic_folder']);
        }

        // 4. Fallback if no folder specified (Initial load state)
        if ($folder_id === false && is_user_logged_in()) {
            $folder_id = -1; // Default All Files
        }

        // If a specific folder (-1 is All Files) is requested
        if ($folder_id !== false && $folder_id != -1) {
            $folder_id = intval($folder_id);
            $relationships_table = $wpdb->prefix . 'mediamatic_relationships';

            if ($folder_id === 0) {
                // Uncategorized: Find media NOT IN any *visible* folder
                $folders_table = $wpdb->prefix . 'mediamatic_folders';

                $clauses['join'] .= " LEFT JOIN {$relationships_table} mediamatic_rel ON {$wpdb->posts}.ID = mediamatic_rel.object_id AND mediamatic_rel.folder_id IN (SELECT id FROM {$folders_table} WHERE user_id IS NULL OR user_id = 0)"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter

                // Add WHERE condition to only keep rows without a matched relationship
                $where_appendix = " AND mediamatic_rel.folder_id IS NULL";
                $clauses['where'] .= $where_appendix;
            } else {
                // Specific Folder: Find media mapped to this folder ID
                $clauses['join'] .= " INNER JOIN {$relationships_table} mediamatic_rel ON {$wpdb->posts}.ID = mediamatic_rel.object_id"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter

                $where_appendix = $wpdb->prepare(" AND mediamatic_rel.folder_id = %d", $folder_id);
                $clauses['where'] .= $where_appendix;
            }
        }

        return $clauses;
    }
}
