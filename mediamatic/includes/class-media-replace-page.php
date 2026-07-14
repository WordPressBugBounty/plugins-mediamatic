<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Media_Replace_Page
 *
 * Handles the dedicated page for media replacement.
 */
class Media_Replace_Page
{
    private $page_slug = 'mediamatic-replace';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_post_mediamatic_replace', [$this, 'handle_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page identification for asset enqueuing, no data mutation.
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($current_page !== $this->page_slug) {
            return;
        }

        // CSS
        wp_enqueue_style('mediamatic-replace', MEDIAMATIC_URL . 'assets/css/replace-page.css', [], MEDIAMATIC_VERSION);

        // JS - Using a virtual handle for cleaner inline script attachment
        wp_register_script('mediamatic-replace', false, [], MEDIAMATIC_VERSION, true);
        wp_enqueue_script('mediamatic-replace');

        ob_start();
        include MEDIAMATIC_DIR . 'assets/js/replace-page-inline.php';
        $js = ob_get_clean();

        wp_add_inline_script('mediamatic-replace', $js);
    }

    /**
     * Register a hidden submenu page.
     */
    public function register_page()
    {
        add_submenu_page(
            null, // Hidden from menu
            __('Replace Media', 'mediamatic'),
            __('Replace Media', 'mediamatic'),
            'edit_posts',
            $this->page_slug,
            [$this, 'render_page']
        );
    }

    /**
     * Render the replacement page UI.
     */
    public function render_page()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only page, no data mutation.
        $attachment_id = isset($_GET['attachment_id']) ? intval(wp_unslash($_GET['attachment_id'])) : 0;
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_die(esc_html__('Invalid attachment ID.', 'mediamatic'));
        }

        if (!current_user_can('edit_post', $attachment_id)) {
            wp_die(esc_html__('You do not have permission to edit this attachment.', 'mediamatic'));
        }

        $url = wp_get_attachment_url($attachment_id);
        $filename = basename(get_attached_file($attachment_id));
        $mime = get_post_mime_type($attachment_id);
        $is_image = strpos($mime, 'image/') === 0;

        ?>
        

        <div class="mediamatic-replace-wrap">
            <header class="mediamatic-replace-header">
                <h1><?php esc_html_e('Replace Media', 'mediamatic'); ?></h1>
                <p><?php esc_html_e('Upload a new file and choose how to handle the replacement.', 'mediamatic'); ?></p>
            </header>

            <form id="mediamatic-replace-form" method="post" enctype="multipart/form-data"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mediamatic_replace_action', 'mediamatic_replace_nonce'); ?>
                <input type="hidden" name="action" value="mediamatic_replace">
                <input type="hidden" name="attachment_id" value="<?php echo esc_attr($attachment_id); ?>">

                <div class="mediamatic-replace-grid">
                    <!-- Current File Card -->
                    <div class="mediamatic-card">
                        <span class="mediamatic-card-title"><?php esc_html_e('Current File', 'mediamatic'); ?></span>
                        <div class="mediamatic-preview-box">
                            <?php if ($is_image): ?>
                                <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($filename); ?>">
                            <?php else: ?>
                                <div class="mediamatic-preview-placeholder">
                                    <span class="dashicons dashicons-media-default"
                                        style="font-size: 48px; width: 48px; height: 48px;"></span>
                                    <span><?php echo esc_html($mime); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mediamatic-file-info">
                            <p><strong><?php esc_html_e('Name:', 'mediamatic'); ?></strong>
                                <?php echo esc_html($filename); ?></p>
                            <p><strong><?php esc_html_e('Type:', 'mediamatic'); ?></strong> <?php echo esc_html($mime); ?>
                            </p>
                        </div>
                    </div>

                    <!-- New File Card (Unified) -->
                    <div class="mediamatic-card">
                        <span class="mediamatic-card-title"><?php esc_html_e('New File', 'mediamatic'); ?></span>

                        <div id="mediamatic-upload-container" class="mediamatic-upload-container"
                            onclick="document.getElementById('mediamatic-file-input').click()">
                            <div id="mediamatic-upload-zone" class="mediamatic-upload-zone">
                                <div id="mediamatic-empty-state" class="mediamatic-preview-placeholder">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                    <p><strong><?php esc_html_e('Click or drag to upload', 'mediamatic'); ?></strong></p>
                                    <p><?php
                                    // translators: %s: MIME type of the current file, e.g. "image/jpeg".
                                    printf(esc_html__('Supports: %s', 'mediamatic'), esc_html($mime));
                                    ?></p>
                                </div>
                                <div id="mediamatic-file-preview"
                                    style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center;">
                                    <!-- Preview content injected here -->
                                </div>
                            </div>

                            <div id="mediamatic-upload-overlay" class="mediamatic-upload-overlay">
                                <span class="dashicons dashicons-upload"></span>
                                <span><?php esc_html_e('Click to change file', 'mediamatic'); ?></span>
                            </div>

                            <input type="file" id="mediamatic-file-input" name="file" required accept="<?php echo esc_attr($mime); ?>"
                                style="display: none;">
                        </div>

                        <div id="mediamatic-new-file-info" class="mediamatic-file-info" style="visibility: hidden;">
                            <p><strong><?php esc_html_e('New Name:', 'mediamatic'); ?></strong> <span
                                    id="mediamatic-new-filename">-</span>
                            </p>
                            <p><strong><?php esc_html_e('New Size:', 'mediamatic'); ?></strong> <span
                                    id="mediamatic-new-filesize">-</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mediamatic-options-container">
                    <!-- Replacement Options -->
                    <div class="mediamatic-option-group">
                        <h3><?php esc_html_e('Replacement Logic', 'mediamatic'); ?></h3>

                        <label class="mediamatic-choice active">
                            <input type="radio" name="replace_type" value="replace" checked>
                            <span class="mediamatic-choice-text">
                                <span
                                    class="mediamatic-choice-title"><?php esc_html_e('Just replace the file', 'mediamatic'); ?></span>
                                <span
                                    class="mediamatic-choice-desc"><?php esc_html_e('Keeps the original filename. Only the file content updates.', 'mediamatic'); ?></span>
                            </span>
                        </label>

                        <label class="mediamatic-choice">
                            <input type="radio" name="replace_type" value="replace_and_search">
                            <span class="mediamatic-choice-text">
                                <span
                                    class="mediamatic-choice-title"><?php esc_html_e('Replace and rename', 'mediamatic'); ?></span>
                                <span
                                    class="mediamatic-choice-desc"><?php esc_html_e('Uses the new filename and updates all references in your database.', 'mediamatic'); ?></span>
                            </span>
                        </label>
                    </div>

                    <!-- Date Options -->
                    <div class="mediamatic-option-group">
                        <h3><?php esc_html_e('Date & Time', 'mediamatic'); ?></h3>

                        <label class="mediamatic-choice active">
                            <input type="radio" name="timestamp_replace" value="keep" checked>
                            <span class="mediamatic-choice-text">
                                <span
                                    class="mediamatic-choice-title"><?php esc_html_e('Keep original date', 'mediamatic'); ?></span>
                                <span
                                    class="mediamatic-choice-desc"><?php esc_html_e('Retain the original upload timestamp.', 'mediamatic'); ?></span>
                            </span>
                        </label>

                        <label class="mediamatic-choice">
                            <input type="radio" name="timestamp_replace" value="current">
                            <span class="mediamatic-choice-text">
                                <span
                                    class="mediamatic-choice-title"><?php esc_html_e('Set to current time', 'mediamatic'); ?></span>
                                <span
                                    class="mediamatic-choice-desc"><?php esc_html_e('Update the date to right now.', 'mediamatic'); ?></span>
                            </span>
                        </label>

                        <label class="mediamatic-choice" id="mediamatic-custom-date-choice">
                            <input type="radio" name="timestamp_replace" value="custom">
                            <span class="mediamatic-choice-text">
                                <span class="mediamatic-choice-title"><?php esc_html_e('Set custom date', 'mediamatic'); ?></span>
                                <span
                                    class="mediamatic-choice-desc"><?php esc_html_e('Manually specify a historical or future date.', 'mediamatic'); ?></span>
                            </span>
                            <div class="mediamatic-date-input">
                                <div class="mediamatic-date-field">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <input type="date" name="custom_date" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                                </div>
                                <div class="mediamatic-date-field">
                                    <span class="dashicons dashicons-clock"></span>
                                    <input type="time" name="custom_time" value="<?php echo esc_attr(gmdate('H:i')); ?>">
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mediamatic-actions">
                    <button type="submit" class="mediamatic-btn mediamatic-btn-primary">
                        <span class="dashicons dashicons-upload" style="margin-right: 8px;"></span>
                        <?php esc_html_e('Start Replacement', 'mediamatic'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="mediamatic-btn mediamatic-btn-secondary">
                        <?php esc_html_e('Cancel', 'mediamatic'); ?>
                    </a>
                </div>
            </form>
        </div>

        
        <?php
    }

    /**
     * Handle the form submission.
     */
    public function handle_submission()
    {
        if (!isset($_POST['mediamatic_replace_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mediamatic_replace_nonce'])), 'mediamatic_replace_action')) {
            wp_die(esc_html__('Security check failed.', 'mediamatic'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval(wp_unslash($_POST['attachment_id'])) : 0;

        if (!current_user_can('edit_post', $attachment_id)) {
            wp_die(esc_html__('You do not have permission to edit this attachment.', 'mediamatic'));
        }

        if (empty($_FILES['file']['name'])) {
            wp_safe_redirect(add_query_arg('error', 'no_file', admin_url('upload.php')));
            exit;
        }

        $custom_date = isset($_POST['custom_date']) ? sanitize_text_field(wp_unslash($_POST['custom_date'])) : '';
        $custom_time = isset($_POST['custom_time']) ? sanitize_text_field(wp_unslash($_POST['custom_time'])) : '';
        $combined_date = '';

        if (!empty($custom_date) && !empty($custom_time)) {
            $combined_date = $custom_date . ' ' . $custom_time;
        }

        $options = [
            'replace_type' => isset($_POST['replace_type']) ? sanitize_text_field(wp_unslash($_POST['replace_type'])) : 'replace',
            'timestamp_replace' => isset($_POST['timestamp_replace']) ? sanitize_text_field(wp_unslash($_POST['timestamp_replace'])) : 'keep',
            'custom_date' => $combined_date,
        ];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES data handled by wp_handle_upload internally.
        $result = Media_Replacer::replace($attachment_id, $_FILES['file'], $options);

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        // Redirect back to attachment editor or library
        wp_safe_redirect(add_query_arg(['item' => $attachment_id, 'replaced' => '1'], admin_url('upload.php')));
        exit;
    }
}
