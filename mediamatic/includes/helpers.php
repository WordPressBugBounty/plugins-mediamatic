<?php
/**
 * Procedural helper functions for Mediamatic.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mediamatic_get_setting')) {
    /**
     * Get a specific setting from the plugin options.
     *
     * @param string $key     The setting key.
     * @param mixed  $default Default value if not set.
     * @return mixed
     */
    function mediamatic_get_setting($key, $default = null)
    {
        $options = get_option('mediamatic_settings', []);
        return isset($options[$key]) ? $options[$key] : $default;
    }
}

if (!function_exists('mediamatic_sanitize_folder_name')) {
    /**
     * Sanitize a folder name.
     *
     * @param string $name
     * @return string
     */
    function mediamatic_sanitize_folder_name($name)
    {
        return sanitize_text_field(wp_unslash($name));
    }
}
