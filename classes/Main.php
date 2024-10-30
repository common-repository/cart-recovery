<?php

namespace Ademti\Crfw;

/**
 * Main plugin class, responsible for triggering everything.
 */
class Main {

	/**
	 * @var string
	 */
	private $base_url;

	/**
	 * @var \Ademti\Crfw\Settings
	 */
	private $settings_instance;

	/**
	 * @var \Ademti\Crfw\CronHandler
	 */
	private $cron_instance;

	/**
	 * @var int
	 */
	private $db_version;

	/**
	 * @var GdprExporter
	 */
	private $gdpr_exporter_instance;

	/**
	 * @var GdprEraser
	 */
	private $gdpr_eraser_instance;

	/**
	 * @var RecoveredCartNotificationEmails
	 */
	private $recovered_cart_notification_emails;

	/**
	 * Constructor
	 */
	public function __construct( $base_url ) {
		$this->db_version = CRFW_DB_VERSION;
		$this->base_url   = $base_url;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		// Has to run after 10 so that settings have been loaded. See Settings::init().
		// Eurgh.
		add_action( 'init', array( $this, 'init' ), 15 );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_filter( 'crfw_campaign_classes', array( $this, 'campaign_classes' ) );
	}

	/**
	 * Register a fifteen minute cron interval.
	 *
	 * @param array $schedules Array of current schedules.
	 *
	 * @return array             Modified array of schedules.
	 */
	public function cron_schedules( $schedules ) {
		$schedules['crfw5m'] = array(
			'interval' => '300',
			'display'  => __( 'Every 5 minutes', 'cart-recovery' ),
		);

		return $schedules;
	}

	// Allow third parties to get our settings instance.
	public function get_settings() {
		return $this->settings_instance;
	}

	/**
	 * Enqueue the main JS file.
	 */
	public function enqueue_scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script(
			'crfw',
			$this->settings_instance->base_url . "/js/cart-recovery-for-wordpress{$min}.js",
			array( 'jquery' ),
			CRFW_VERSION,
			true
		);
		$js_info = [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ];

		wp_localize_script(
			'crfw',
			'crfw_settings',
			apply_filters(
				'crfw_js_info',
				$js_info
			)
		);
	}

	/**
	 * Implements plugins_loaded().
	 */
	public function plugins_loaded() {
		$this->settings_instance = new Settings( $this, $this->base_url );
		if ( empty( $this->settings_instance->engine ) ) {
			return;
		}
		$this->cron_instance = new CronHandler( $this->settings_instance );

		// Register any campaign classes.
		$campaign_classes = apply_filters( 'crfw_campaign_classes', array() );
		foreach ( $campaign_classes as $class_info ) {
			$class_name = $class_info['class'];
			$this->settings_instance->register_campaign(
				new $class_name( $this->settings_instance, $class_info['data'] )
			);
		}

		// Register the GdprExporter
		$this->gdpr_exporter_instance = new GdprExporter();
		$this->gdpr_exporter_instance->run();

		// Register the GdprEraser
		$this->gdpr_eraser_instance = new GdprEraser();
		$this->gdpr_eraser_instance->run();

		// Register the RecoveredCartNotificationEmails class.
		$this->recovered_cart_notification_emails = new RecoveredCartNotificationEmails( $this->settings_instance );
		$this->recovered_cart_notification_emails->run();
	}

	/**
	 * Register the built-in campaign.
	 *
	 * @param array $campaigns Array of existing campaigns.
	 *
	 * @return array             Modified array of campaigns.
	 */
	public function campaign_classes( $campaigns ) {
		$campaigns[] = array(
			'class' => '\Ademti\Crfw\Campaigns\SimpleCampaign',
			'data'  => array(),
		);

		return $campaigns;
	}

	/**
	 * Ensure that a cron event if scheduled if it needs to be. Schedule one if not.
	 */
	private function ensure_cron_hooked() {
		// Hook the cron callback.
		add_action( 'crfw_cron', array( $this->cron_instance, 'cron' ) );

		// Check for an existing scheduled event.
		$next_ts = wp_next_scheduled( 'crfw_cron' );
		if ( false !== $next_ts && $next_ts > time() ) {
			// There is a future event scheduled.
			// We have nothing else to do.
			return;
		}
		if ( false === $next_ts ) {
			// No scheduled event found. Add one for six minutes in the future.
			wp_schedule_event( time() + 6, 'crfw5m', 'crfw_cron' );

			return;
		}
		// Scheduled event found, but is in the past.
		// Show a warning if it's more than 10 mins out of date.
		if ( $next_ts < ( time() - 600 ) ) {
			add_action( 'admin_notices', array( $this, 'show_overdue_cron_notice' ) );
		}
	}

	public function show_overdue_cron_notice() {
		?>
		<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'Cart Recovery processing is scheduled, but it does not look like it is running. Please check your WordPress scheduling system (cron) is working correctly and is not disabled.', 'cart-recovery' ); ?>
		</p>
		</div>
		<?php
	}

	/**
	 * Implements init().
	 *
	 * Set up translation for the plugin.
	 */
	public function init() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'cart-recovery' );
		load_textdomain( 'cart-recovery', WP_LANG_DIR . '/cart-recovery-for-wordpress/cart-recovery-' . $locale . '.mo' );
		load_plugin_textdomain( 'cart-recovery', false, basename( __DIR__ ) . '/languages/' );

		// Ensure the cron job is hooked.
		$this->ensure_cron_hooked();
	}

	/**
	 * Fires on admin_init().
	 *
	 * Check for any required database schema updates.
	 */
	public function admin_init() {
		$this->check_db_version();
	}

	/**
	 * Check for pending upgrades, and run them if required.
	 */
	public function check_db_version() {
		$current_db_version = (int) get_option( 'crfw_db_version', 1 );
		// Bail if we're already up to date.
		if ( $current_db_version >= $this->db_version ) {
			return;
		}
		// Otherwise, check for, and run updates.
		foreach ( range( $current_db_version + 1, $this->db_version ) as $version ) {
			if ( is_callable( array( $this, 'upgrade_db_to_' . $version ) ) ) {
				$this->{'upgrade_db_to_' . $version}();
				update_option( 'crfw_db_version', $version );
			} else {
				update_option( 'crfw_db_version', $version );
			}
		}
	}

	/**
	 * Database upgrade routine for DB version 3.
	 *
	 * Adds index on status to the _crfw_cart table.
	 * Resizes the email field from 2014 to 1024 on _crfw_cart table.
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 */
	private function upgrade_db_to_3() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		// Create the tables we need.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name = $wpdb->prefix . 'crfw_cart';
		$sql        = "CREATE TABLE $table_name (
		            id INT(11) NOT NULL AUTO_INCREMENT,
		            email VARCHAR(1024) NOT NULL,
		            first_name VARCHAR(1024),
		            surname VARCHAR(1024),
		            status VARCHAR(16) NOT NULL DEFAULT 'pending',
		            cart_details TEXT,
		            created INT(11) NOT NULL,
		            updated INT(11) NOT NULL,
		            PRIMARY KEY  (id),
		            KEY status_idx (status)
		        ) $charset_collate";
		dbDelta( $sql );
	}

	/**
	 * Upgrades the database to version 4.
	 *
	 * Adds a completed date to the cart table. Make a note of when we started
	 * tracking cart completion values.
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 */
	private function upgrade_db_to_4() {
		global $wpdb;

		// Note the date when we started tracking cart IDs.
		add_option( 'crfw_cart_value_tracking_started', time() );

		// Add the "completed" field to the cart table.
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name = $wpdb->prefix . 'crfw_cart';
		$sql        = "CREATE TABLE $table_name (
					id int(11) NOT NULL AUTO_INCREMENT,
					email varchar(1024) NOT NULL,
					first_name varchar(1024),
					surname varchar(1024),
					status varchar(16) NOT NULL DEFAULT 'pending',
					cart_details text,
					created int(11) NOT NULL,
					updated int(11) NOT NULL,
					completed int(11),
					PRIMARY KEY  (id),
					KEY status_idx (status),
					KEY completed_idx (completed)
				) $charset_collate";
		dbDelta( $sql );
	}

	/**
	 * Triggers the cron jobs to anonymise any user IDs left in the cart meta table.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 */
	private function upgrade_db_to_5() {
		add_option( 'crfw_anon_meta_clearup_processed_until', 0 );
	}

	/**
	 * Output the admin header wrappers.
	 */
	public function admin_header( $active_tab = null ) {
		$this->settings_instance->admin_header( $active_tab );
	}

	/**
	 * Output the admin footer wrappers.
	 */
	public function admin_footer( $active_tab = null ) {
		$this->settings_instance->admin_footer( $active_tab );
	}
}
