<?php
namespace Mediamatic;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 *
 * Handles the Mediamatic settings page (tabbed UI).
 */
class Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_mediamatic_settings', [$this, 'on_settings_saved'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_mediamatic') {
            return;
        }

        // CSS
        wp_enqueue_style('mediamatic-settings', MEDIAMATIC_URL . 'assets/css/settings.css', [], MEDIAMATIC_VERSION);

        // JS
        wp_enqueue_script('mediamatic-settings', '', ['jquery'], MEDIAMATIC_VERSION, true);
        
        ob_start();
        $nonce_val = wp_create_nonce('mediamatic_ajax');
        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        include MEDIAMATIC_DIR . 'assets/js/settings-inline.php';
        $js = ob_get_clean();
        
        wp_add_inline_script('mediamatic-settings', $js);
    }

    /**
     * Triggered when settings are saved.
     */
    public function on_settings_saved($old_value, $new_value)
    {
        Permissions::sync_role_capabilities($new_value);
    }

    public function add_settings_page()
    {
        add_menu_page(
            __('Mediamatic', 'mediamatic'),           // Page title
            __('Mediamatic', 'mediamatic'),           // Menu label
            'manage_options',                                 // Capability
            'mediamatic',                                 // Slug
            [$this, 'render_settings_page'],                  // Callback
            'dashicons-category',                             // Icon
            80                                                // Position (after Media = 10, after Appearance = 60)
        );
    }

    public function register_settings()
    {
        register_setting('mediamatic_settings_group', 'mediamatic_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        $checkboxes = ['delete_on_uninstall', 'per_user_folders', 'show_breadcrumb'];
        foreach ($checkboxes as $key) {
            $sanitized[$key] = !empty($input[$key]) ? 1 : 0;
        }
        // Folder counter mode: 'direct' or 'recursive'
        $sanitized['folder_counter'] = (isset($input['folder_counter']) && $input['folder_counter'] === 'recursive')
            ? 'recursive'
            : 'direct';

        // Folder Access Mode
        $sanitized['mediamatic_folder_mode'] = (isset($input['mediamatic_folder_mode']) && $input['mediamatic_folder_mode'] === 'private')
            ? 'private'
            : 'global';


        // Role arrays
        $arrays = [
            'mediamatic_private_allowed_roles',
            'mediamatic_global_create_roles',
            'mediamatic_global_edit_roles',
            'mediamatic_global_delete_roles'
        ];

        foreach ($arrays as $arr_key) {
            if (!empty($input[$arr_key]) && is_array($input[$arr_key])) {
                $sanitized[$arr_key] = array_map('sanitize_key', $input[$arr_key]);
            } else {
                $sanitized[$arr_key] = [];
            }
        }

        // Allowed post types — validate against real registered types
        $all_types = array_keys(get_post_types(['public' => true], 'names'));
        if (!empty($input['allowed_post_types']) && is_array($input['allowed_post_types'])) {
            $sanitized['allowed_post_types'] = array_values(
                array_intersect(array_map('sanitize_key', $input['allowed_post_types']), $all_types)
            );
        } else {
            $sanitized['allowed_post_types'] = [];
        }
        // Hardcode global mode and no breadcrumb for free version
        $sanitized['mediamatic_folder_mode'] = 'global';
        $sanitized['show_breadcrumb'] = 0;

        return $sanitized;
    }

    public static function get($key, $default = false)
    {
        $options = get_option('mediamatic_settings', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $options = get_option('mediamatic_settings', []);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only page.
        $saved = isset($_GET['settings-updated']) && sanitize_key(wp_unslash($_GET['settings-updated']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only page.
        $active = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $nonce_val = wp_create_nonce('mediamatic_ajax');
        $ajax_url = esc_url(admin_url('admin-ajax.php'));
        ?>
        

        <div class="mediamatic-wrap">

            <!-- Header -->
            <div class="mediamatic-header">
                <h1><?php esc_html_e('Mediamatic', 'mediamatic'); ?></h1>
                <span class="mediamatic-badge">Settings</span>
            </div>

            <?php if ($saved): ?>
                <div class="mediamatic-notice" id="mediamatic-saved-notice">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <?php esc_html_e('Settings saved successfully.', 'mediamatic'); ?>
                </div>
            <?php endif; ?>



            <!-- Tab nav -->
            <nav class="mediamatic-tabs">
                <a href="?page=mediamatic&tab=general" class="mediamatic-tab <?php echo $active === 'general' ? 'active' : ''; ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <path
                            d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                    </svg>
                    <?php esc_html_e('General', 'mediamatic'); ?>
                </a>
                <a href="?page=mediamatic&tab=import" class="mediamatic-tab <?php echo $active === 'import' ? 'active' : ''; ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    <?php esc_html_e('Import / Export', 'mediamatic'); ?>
                </a>
            </nav>

            <!-- ── Tab: General ── -->
            <div class="mediamatic-panel <?php echo $active === 'general' ? 'active' : ''; ?>" id="tab-general">
                <form action="options.php" method="post">
                    <?php settings_fields('mediamatic_settings_group'); ?>

                    <div class="mediamatic-card">
                        <div class="mediamatic-card-head">
                            <p class="mediamatic-card-title">
                                <?php esc_html_e('Folder Access Mode', 'mediamatic'); ?>
                            </p>
                            <p class="mediamatic-card-desc">
                                <?php esc_html_e('Choose how folders are shared and managed across your site.', 'mediamatic'); ?>
                            </p>
                        </div>
                        <div class="mediamatic-row" style="flex-direction:column;gap:10px;">
                            <?php $folder_mode = $options['mediamatic_folder_mode'] ?? 'global'; ?>
                            <label class="mediamatic-radio-label">
                                <input type="radio" name="mediamatic_settings[mediamatic_folder_mode]" value="global"
                                    <?php checked('global', $folder_mode); ?>
                                    onchange="document.getElementById('mediamatic-role-global').style.display='block';document.getElementById('mediamatic-role-private').style.display='none';" />
                                <?php esc_html_e('Global Shared Folders (Folders are visible to all users)', 'mediamatic'); ?>
                            </label>
                            

                        </div>
                    </div>



                    <!-- Post Types -->
                    <div class="mediamatic-card">
                        <div class="mediamatic-card-head">
                            <p class="mediamatic-card-title">
                                <?php esc_html_e('Which post types to use with Mediamatic', 'mediamatic'); ?>
                            </p>
                            <p class="mediamatic-card-desc">
                                <?php esc_html_e('Enable the folder sidebar for these post type editors.', 'mediamatic'); ?>
                            </p>
                        </div>
                        <div class="mediamatic-row" style="flex-direction:column;gap:10px;">
                            <?php
                            $allowed = $options['allowed_post_types'] ?? [];
                            $public_types = get_post_types(['public' => true], 'objects');
                            // Exclude 'attachment' — media library always has the sidebar
                            unset($public_types['attachment']);
                            foreach ($public_types as $pt):
                                ?>
                                <label class="mediamatic-radio-label">
                                    <input type="checkbox" name="mediamatic_settings[allowed_post_types][]"
                                        value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $allowed, true)); ?> />
                                    <?php echo esc_html($pt->labels->singular_name ?: $pt->name); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($public_types)): ?>
                                <p style="font-size:12px;color:#8c8f94;margin:0;">
                                    <?php esc_html_e('No public post types found.', 'mediamatic'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Folder Counter -->
                    <div class="mediamatic-card">
                        <div class="mediamatic-card-head">
                            <p class="mediamatic-card-title"><?php esc_html_e('Folder counter', 'mediamatic'); ?></p>
                            <p class="mediamatic-card-desc">
                                <?php esc_html_e('Choose how the file count badge on each folder is calculated.', 'mediamatic'); ?>
                            </p>
                        </div>
                        <div class="mediamatic-row" style="flex-direction:column;gap:10px;">
                            <?php $counter_mode = $options['folder_counter'] ?? 'direct'; ?>
                            <label class="mediamatic-radio-label">
                                <input type="radio" name="mediamatic_settings[folder_counter]" value="direct" <?php checked('direct', $counter_mode); ?> />
                                <?php esc_html_e('Count files in each folder', 'mediamatic'); ?>
                            </label>
                            <label class="mediamatic-radio-label">
                                <input type="radio" name="mediamatic_settings[folder_counter]" value="recursive" <?php checked('recursive', $counter_mode); ?> />
                                <?php esc_html_e('Count files in both parent folder and subfolders', 'mediamatic'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="mediamatic-card">
                        <div class="mediamatic-card-head">
                            <p class="mediamatic-card-title"><?php esc_html_e('Data & Privacy', 'mediamatic'); ?></p>
                        </div>
                        <div class="mediamatic-row">
                            <div class="mediamatic-row-label">
                                <strong><?php esc_html_e('Delete data on uninstall', 'mediamatic'); ?></strong>
                                <span><?php esc_html_e('Remove all folders and plugin settings when the plugin is deleted.', 'mediamatic'); ?></span>
                            </div>
                            <label class="mediamatic-setting-toggle">
                                <input type="checkbox" name="mediamatic_settings[delete_on_uninstall]" value="1" <?php checked(1, !empty($options['delete_on_uninstall'])); ?> />
                                <span class="mediamatic-setting-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="mediamatic-footer">
                        <?php submit_button(__('Save Changes', 'mediamatic'), 'primary', 'submit', false); ?>
                        <span
                            class="mediamatic-footer-hint"><?php esc_html_e('Mediamatic — Media Library Folders', 'mediamatic'); ?></span>
                    </div>
                </form>
            </div>

            <!-- ── Tab: Import / Export ── -->
            <div class="mediamatic-panel <?php echo $active === 'import' ? 'active' : ''; ?>" id="tab-import">
                <div class="mediamatic-card">
                    <div class="mediamatic-card-head">
                        <p class="mediamatic-card-title"><?php esc_html_e('Export', 'mediamatic'); ?></p>
                        <p class="mediamatic-card-desc">
                            <?php esc_html_e('Download your current folder structure as a CSV file for backup or migration.', 'mediamatic'); ?>
                        </p>
                    </div>
                    <div class="mediamatic-ie-block">
                        <p class="mediamatic-ie-title"><?php esc_html_e('Export folders (CSV)', 'mediamatic'); ?></p>
                        <p class="mediamatic-ie-desc">
                            <?php esc_html_e('Exports your visible folders. The CSV can be re-imported here or into another site.', 'mediamatic'); ?>
                        </p>
                        <div class="mediamatic-ie-actions">
                            <a class="button"
                                href="<?php echo esc_url(admin_url('admin-ajax.php?action=mediamatic_export_csv&nonce=' . $nonce_val)); ?>"
                                download>
                                ↓ <?php esc_html_e('Download CSV', 'mediamatic'); ?>
                            </a>
                        </div>
                    </div>
                </div>


            </div>


            <!-- ── Tab: Backups ── -->


        </div><!-- .mediamatic-wrap -->

        
        <?php
    }
}
