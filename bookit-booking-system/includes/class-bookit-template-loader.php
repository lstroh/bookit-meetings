<?php
/**
 * HOW TO OVERRIDE BOOKIT TEMPLATES IN YOUR THEME
 * ================================================
 * Copy any template from:
 *   wp-content/plugins/bookit-booking-system/public/templates/
 *
 * To your theme at:
 *   wp-content/themes/{your-theme}/bookit/
 *
 * Example:
 *   Plugin default: .../bookit-booking-system/public/templates/
 *                   booking-wizard-v2-step-1.php
 *   Theme override: .../themes/your-theme/bookit/
 *                   booking-wizard-v2-step-1.php
 *
 * Bookit will automatically use your override file instead of the
 * plugin default. Child themes are supported - Bookit checks the
 * child theme first, then the parent theme, then the plugin default.
 *
 * IMPORTANT: When the plugin updates, check your overridden templates
 * for changes. Outdated overrides may break if the template's expected
 * variables change. The current plugin version is in BOOKIT_VERSION.
 *
 * CSS CUSTOMISATION
 * =================
 * Override CSS custom properties in your theme stylesheet to change
 * colours, typography, and spacing without touching plugin files:
 *
 *   :root {
 *     --bookit-primary:       #E91E63 !important;
 *     --bookit-border-radius: 4px !important;
 *     --bookit-font-family:   'Poppins', sans-serif !important;
 *   }
 *
 * NOTE: The !important flag is required because the plugin stylesheet
 * loads after the theme stylesheet. Without it, the plugin's own :root
 * declarations will take precedence over theme overrides.
 *
 * Available CSS custom properties (defined in booking-wizard-v2.css):
 *
 *   Colours:    --bookit-primary, --bookit-primary-hover,
 *               --bookit-primary-light, --bookit-accent,
 *               --bookit-text-primary, --bookit-text-secondary,
 *               --bookit-text-muted, --bookit-text-inverse,
 *               --bookit-bg-page, --bookit-bg-card, --bookit-bg-input,
 *               --bookit-border, --bookit-border-focus,
 *               --bookit-color-success, --bookit-color-warning,
 *               --bookit-color-error, --bookit-color-info
 *
 *   Shape:      --bookit-border-radius, --bookit-border-radius-sm,
 *               --bookit-shadow-sm, --bookit-shadow
 *
 *   Typography: --bookit-font-family, --bookit-font-size-sm,
 *               --bookit-font-size-base, --bookit-font-size-lg,
 *               --bookit-font-size-xl, --bookit-line-height
 *
 *   Spacing:    --bookit-spacing-xs, --bookit-spacing-sm,
 *               --bookit-spacing-md, --bookit-spacing-lg,
 *               --bookit-spacing-xl
 *
 *   Buttons:    --bookit-btn-primary-bg, --bookit-btn-primary-text,
 *               --bookit-btn-radius
 *
 *   Steps:      --bookit-step-active-bg, --bookit-step-done-bg,
 *               --bookit-step-inactive-bg
 */
/**
 * Template loader with theme override support.
 *
 * Themes can override any Bookit template by placing a file at:
 *   {theme}/bookit/{template-name}.php
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Template_Loader {

	/**
	 * Load a template, checking theme overrides first.
	 *
	 * Checks in order:
	 *   1. {active-theme}/bookit/{template-name}.php
	 *   2. {parent-theme}/bookit/{template-name}.php (child theme support)
	 *   3. {plugin}/public/templates/{template-name}.php (fallback)
	 *
	 * @param string $template_name Filename e.g. 'booking-wizard-v2-step-1.php'
	 * @param array  $args          Variables to extract into template scope.
	 * @param bool   $return        If true, return HTML string; if false, echo.
	 * @return string|void
	 */
	public static function get_template( $template_name, $args = array(), $return = false ) {
		$path = self::locate_template( $template_name );

		if ( ! file_exists( $path ) ) {
			return $return ? '' : null;
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args, EXTR_SKIP );
		}

		if ( $return ) {
			ob_start();
			include $path;
			return ob_get_clean();
		}

		include $path;
	}

	/**
	 * Locate the template file, returning the override path if it exists,
	 * otherwise the plugin default path.
	 *
	 * @param string $template_name Filename e.g. 'booking-confirmed-v2.php'
	 * @return string Absolute path to the template file.
	 */
	public static function locate_template( $template_name ) {
		$theme_template = get_stylesheet_directory() . '/' . self::theme_template_directory() . '/' . $template_name;
		if ( file_exists( $theme_template ) ) {
			return $theme_template;
		}

		$parent_theme_template = get_template_directory() . '/' . self::theme_template_directory() . '/' . $template_name;
		if ( file_exists( $parent_theme_template ) ) {
			return $parent_theme_template;
		}

		return self::plugin_template_path() . $template_name;
	}

	/**
	 * Return the absolute path to the plugin's default templates directory.
	 * Includes trailing slash.
	 *
	 * @return string
	 */
	public static function plugin_template_path() {
		return BOOKIT_PLUGIN_DIR . 'public/templates/';
	}

	/**
	 * Return the theme override subdirectory name.
	 * Themes place overrides in {theme}/bookit/
	 *
	 * @return string 'bookit'
	 */
	public static function theme_template_directory() {
		return 'bookit';
	}
}
