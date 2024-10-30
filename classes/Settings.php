<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Campaigns\AbstractCampaign;
use function sanitize_key;

/**
 * Settings class.
 */
class Settings {

	/**
	 * @var Main
	 */
	private $main;

	/**
	 * @var array
	 */
	private $settings = array();

	/**
	 * @var string[]
	 */
	private $engines = array(
		'edd'         => 'Ademti\Crfw\Engines\Edd',
		'rcp'         => 'Ademti\Crfw\Engines\RestrictContentPro',
		'woocommerce' => 'Ademti\Crfw\Engines\Woocommerce',
		'wpecommerce' => 'Ademti\Crfw\Engines\Wpecommerce',
	);

	/**
	 * @var string
	 */
	public $engine_type;

	/**
	 * @var AbstractCampaign
	 */
	public $engine;

	/**
	 * Constructor.
	 *
	 * Loads the settings from the database.
	 */
	public function __construct( Main $main, $base_url ) {

		// Store the main class.
		$this->main        = $main;
		$this->engine_type = $this->find_engine();
		if ( empty( $this->engine_type ) ) {
			return;
		}
		$engine_class               = $this->engines[ $this->engine_type ];
		$this->engine               = new $engine_class( $this );
		$this->settings['base_url'] = $base_url;

		// Skip if we're installing.
		$current_db_version = get_option( 'crfw_db_version' );
		if ( false === $current_db_version ) {
			return;
		}

		// Queue up settings load at end of plugins_loaded.
		// Allows add-ons to register settings.
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 90 );

		// Hooks for settings pages.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Load the settings forms.
		if ( is_admin() ) {
			$this->form = new SettingsForm( $this );
			add_action( 'admin_init', array( $this->form, 'settings_init' ) );
		}
	}

	public function plugins_loaded() {
		// Read in all of the various options. If any are missing, apply a filter to allow
		// a tab specifier to provide defaults.
		foreach ( array_keys( $this->get_settings_tabs() ) as $tab ) {
			$options = get_option( 'crfw_settings_' . $tab );
			if ( is_array( $options ) ) {
				$this->settings = array_merge( $this->settings, $options );
			} else {
				$this->settings = array_merge(
					$this->settings,
					$this->get_default_settings( $tab )
				);
			}
		}
	}

	/**
	 * Magic getter. Return value from settings, or NULL.
	 */
	public function __get( $key ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		} else {
			return null;
		}
	}

	/**
	 * Magic isset.
	 */
	public function __isset( $key ) {
		return isset( $this->settings[ $key ] );
	}

	/**
	 * Register the admin menu.
	 */
	public function add_admin_menu() {
		$settings_page = add_menu_page(
			__( 'Cart recovery', 'cart-recovery' ),
			__( 'Cart recovery', 'cart-recovery' ),
			'manage_options',
			'cart_recovery_for_wordpress',
			array( $this, 'options_page' ),
			'dashicons-cart',
			'49.777'
		);
		add_action( 'load-' . $settings_page, array( $this, 'add_help_tab' ) );
	}

	/**
	 * Add help tab if there is one available.
	 */
	public function add_help_tab() {

		$active_tab = $this->get_active_tab();

		$help_callback = apply_filters(
			'crfw_settings_help_callback',
			array( $this->form, 'help_' . $active_tab ),
			$active_tab
		);
		if ( is_callable( $help_callback ) ) {
			$screen = get_current_screen();
			$screen->add_help_tab(
				array(
					'id'       => 'crfw_help_' . $active_tab,
					'title'    => __( 'Help', 'cart-recovery' ),
					'callback' => array( $this->form, 'help_' . $active_tab ),
				)
			);
		}
	}

	/**
	 * Add admin CSS.
	 */
	public function enqueue_style() {
		wp_enqueue_style(
			'crfw-admin',
			$this->settings['base_url'] . '/css/cart-recovery-for-wordpress-admin.css',
			array(),
			CRFW_VERSION
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function options_page() {
		$tabs            = $this->get_settings_tabs();
		$active_tab      = $this->get_active_tab();
		$template_loader = new TemplateLoader();

		// Output the settings page.
		$this->admin_header();
		$template_loader->output_template_with_variables(
			'admin',
			'intro-' . $active_tab,
			array()
		);

		if ( ! empty( $tabs[ $active_tab ]['callback'] ) ) {
			$tabs[ $active_tab ]['callback']();
		}
		$this->admin_footer();
	}

	/**
	 * Output the admin header wrappers for a specific tab.
	 */
	public function admin_header( $active_tab = null ) {
		// Get the active tab if none specified.
		if ( is_null( $active_tab ) ) {
			$active_tab = $this->get_active_tab();
		}
		// Add our stylesheet.
		$this->enqueue_style();

		$template_loader = new TemplateLoader();
		// Generate the tab nav.
		$variables = array(
			'tabs' => '',
		);
		$tabs      = $this->get_settings_tabs();
		foreach ( $tabs as $tab => $tab_config ) {
			$tabdata = array(
				'tab'        => $tab,
				'tab_label'  => $tab_config['label'],
				'tab_active' => ( $tab === $active_tab ) ? 'nav-tab-active' : '',
			);

			$variables['tabs'] .= $template_loader->get_template_with_variables( 'admin', 'header-tab', $tabdata );
		}
		$template_loader->output_template_with_variables( 'admin', 'header', $variables );
	}

	/**
	 * Output the admin footer wrappers for a specific tab.
	 */
	public function admin_footer( $active_tab = null ) {
		$template_loader = new TemplateLoader();
		if ( is_null( $active_tab ) ) {
			$active_tab = $this->get_active_tab();
		}
		$template_loader = new TemplateLoader();
		if ( apply_filters( 'crfw_show_admin_footers', true ) ) {
			$template_loader->output_template_with_variables( 'admin', 'footer-' . $active_tab, array() );
		}
		$template_loader->output_template_with_variables( 'admin', 'footer', array() );
	}

	/**
	 * Store the campaigns as they're instantiated.
	 *
	 * @param Campaign $campaign A campaign class instance.
	 *
	 * @return void
	 */
	public function register_campaign( AbstractCampaign $campaign ) {
		$this->settings['campaigns'][ $campaign->get_slug() ] = $campaign;
	}

	/**
	 * Show the status page.
	 */
	public function show_status_page() {
		$status_page = new StatusPage( $this );
		$status_page->render();
	}

	/**
	 * Show the main options page.
	 */
	public function show_main_page() {
		settings_fields( 'crfw_main_plugin_page' );
		do_settings_sections( 'crfw_main_plugin_page' );
		do_action( 'crfw_main_plugin_page' );
		submit_button();
	}

	/**
	 * Return a list of the valid settings tabs.
	 *
	 * Since each tab has its settings stored in an individual option in the database, this
	 * array also controls which options are loaded.
	 *
	 * @return array Array of tab identifiers.
	 */
	private function get_settings_tabs() {
		return apply_filters(
			'crfw_settings_tabs',
			array(
				'status' => array(
					'label'    => __( 'Recovery status', 'cart-recovery' ),
					'callback' => array( $this, 'show_status_page' ),
				),
				'main'   => array(
					'label'    => __( 'Tracking options', 'cart-recovery' ),
					'callback' => array( $this, 'show_main_page' ),
				),
			)
		);
	}

	/**
	 * Get the currently active tab.
	 *
	 * @return string The active tab - sanitised against the list of all available tabs.
	 */
	private function get_active_tab() {
		// Get all tabs.
		$tabs = $this->get_settings_tabs();

		// Grab the active tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'status' ) );

		if ( ! in_array( $active_tab, array_keys( $tabs ), true ) ) {
			$active_tab = 'status';
		}

		return $active_tab;
	}

	/**
	 * Work out some default settings for a tab.
	 *
	 * @return array Array of defaults for this tab.
	 */
	private function get_default_settings( $tab ) {

		$settings            = [];
		$email_template_path = dirname( __DIR__ ) . '/templates/email-default-content.php';
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$default_email_content = file_get_contents( $email_template_path );
		// phpcs:enable
		if ( 'main' === $tab ) {
			$settings = array(
				'crfw_recover_checkout_emails' => 0,
				'crfw_email_from'              => get_bloginfo( 'name' ),
				'crfw_email_from_address'      => get_bloginfo( 'admin_email' ),
			);
		} elseif ( 'email' === $tab ) {
			$settings = array(
				'crfw_email_subject' => 'We miss you...',
				'crfw_email_content' => $default_email_content,
			);
		}
		$settings = apply_filters( 'crfw_default_settings_' . $tab, $settings );
		add_option( 'crfw_settings_' . $tab, $settings );

		return $settings;
	}

	/**
	 * Work out which eCommerce system we're using.
	 *
	 * @return string
	 */
	private function find_engine() {
		if ( class_exists( 'Easy_Digital_Downloads' ) ) {
			return 'edd';
		}
		if ( class_exists( 'WooCommerce' ) ) {
			return 'woocommerce';
		}
		if ( class_exists( 'WP_eCommerce' ) ) {
			return 'wpecommerce';
		}
		if ( defined( 'RCP_PLUGIN_VERSION' ) && version_compare( RCP_PLUGIN_VERSION, '2.6.14', '>=' ) ) {
			return 'rcp';
		}

		return '';
	}
}
