<?php

namespace Ademti\Crfw;

use Gamajo\TemplateLoader as GTemplateLoader;

/**
 * Renders templates with a given set of variables, and returns the content.
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class TemplateLoader extends GTemplateLoader {

	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $filter_prefix;

	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $theme_template_directory;

	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * @since 1.0.0
	 *
	 * @type string
	 */
	protected $plugin_directory;

	/**
	 * Constructor. Stores needed config.
	 */
	public function __construct() {
		$this->filter_prefix             = 'crfw';
		$this->theme_template_directory  = 'crfw';
		$this->plugin_template_directory = 'templates';
		$this->plugin_directory          = __DIR__ . '/../';
	}

	/**
	 * Get the contents of a template with variables substituted.
	 *
	 * @param  string $slug      The template slug (First part of filename)
	 * @param  string $name      The template name (Second half of filename)
	 * @param  array  $variables Variables to be replaced into the template.
	 *
	 * @return string             The rendered output.
	 */
	public function get_template_with_variables( $slug, $name = null, $variables = [] ) {
		ob_start();
		$this->get_template_part( $slug, $name );
		$content = ob_get_clean();
		foreach ( $variables as $key => $value ) {
			$content = str_replace( '{' . $key . '}', $value, $content );
		}
		return $content;
	}

	public function get_template( $slug, $name = null ) {
		ob_start();
		$this->get_template_part( $slug, $name );
		return ob_get_clean();
	}

	/**
	 * Output the contents of a template with variables substituted.
	 *
	 * @param  string $slug      The template slug (First part of filename)
	 * @param  string $name      The template name (Second half of filename)
	 * @param  array  $variables Variables to be replaced into the template.
	 *
	 * @uses   get_template_with_variables()
	 *
	 * @return string             The rendered output.
	 */
	public function output_template_with_variables( $slug, $name = null, $variables = [] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_template_with_variables( $slug, $name, $variables );
	}
}
