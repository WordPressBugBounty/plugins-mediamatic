<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Import_Export
 *
 * Handles CSV import/export, third-party plugin import (FileBird, Folders),
 * and per-user automated daily backups with 30-day retention.
 */
class Import_Export
{
    /** @var Folder_Manager */
    private $folder_manager;

    /** Backup directory inside wp-content/uploads */
    const BACKUP_SUBDIR = 'mediamatic-backups';

    public function __construct($folder_manager)
    {
        $this->folder_manager = $folder_manager;

        // AJAX handlers (admin only)
        add_action('wp_ajax_mediamatic_export_csv', [$this, 'ajax_export_csv']);
    }

    /* ─────────────────────────────────────────────
       CSV EXPORT
    ───────────────────────────────────────────── */

    /**
     * Export folders of current user (or all if per_user_folders off) as CSV.
     */
    public function ajax_export_csv()
    {
        check_ajax_referer('mediamatic_ajax', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_die('Forbidden', 403);
        }

        $folders = $this->folder_manager->get_folders();
        $csv = $this->folders_to_csv($folders);

        $filename = 'mediamatic-folders-' . get_current_user_id() . '-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . esc_attr($filename));
        header('Pragma: no-cache');
        echo $csv; // phpcs:ignore
        exit;
    }

    /**
     * Convert folders array to CSV string.
     */
    private function folders_to_csv(array $folders): string
    {
        $lines = ["id,parent_id,name,color,order_index"];
        foreach ($folders as $f) {
            $lines[] = implode(',', [
                (int) $f['id'],
                (int) $f['parent_id'],
                '"' . str_replace('"', '""', $f['name']) . '"',
                '"' . esc_attr($f['color'] ?? '') . '"',
                (int) ($f['order_index'] ?? 0),
            ]);
        }
        return implode("\n", $lines);
    }

    /* ─────────────────────────────────────────────
       JSON EXPORT (legacy)
    ───────────────────────────────────────────── */

    public function export_json()
    {
        $folders = $this->folder_manager->get_folders();
        return wp_json_encode($folders);
    }
}
