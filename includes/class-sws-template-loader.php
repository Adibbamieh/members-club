<?php
/**
 * Template loader — checks theme for overrides, falls back to plugin.
 *
 * Theme developers can override any template in templates/frontend/
 * by copying it to wp-content/themes/active-theme/sws-members-club/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SWS_Template_Loader {

    /**
     * Locate a template file.
     *
     * Checks:
     * 1. Theme directory: {theme}/sws-members-club/{template_name}
     * 2. Plugin directory: {plugin}/templates/frontend/{template_name}
     *
     * @param string $template_name Template filename (e.g. 'events-listing.php').
     * @return string Full path to template.
     */
    public static function locate( $template_name ) {
        // Check theme override.
        $theme_path = get_stylesheet_directory() . '/sws-members-club/' . $template_name;
        if ( file_exists( $theme_path ) ) {
            return $theme_path;
        }

        // Check parent theme.
        $parent_path = get_template_directory() . '/sws-members-club/' . $template_name;
        if ( $parent_path !== $theme_path && file_exists( $parent_path ) ) {
            return $parent_path;
        }

        // Fall back to plugin default.
        return SWS_PLUGIN_DIR . 'templates/frontend/' . $template_name;
    }

    /**
     * Load a template with variables.
     *
     * @param string $template_name Template filename.
     * @param array  $args          Variables to extract into template scope.
     * @return string Rendered HTML.
     */
    public static function render( $template_name, $args = array() ) {
        $template = self::locate( $template_name );

        if ( ! file_exists( $template ) ) {
            return '';
        }

        // Extract vars into scope.
        if ( ! empty( $args ) ) {
            extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }
}
