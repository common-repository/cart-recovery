<?php

// We need the EDD SL class to be available
if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
}

class CRFW_Plugin_Updater {

	// Instance of the EDD SL updater.
	private $edd_sl_plugin_updater;

	// The main plugin filename that we're performing updates for.
	private $file;

	// The internal slug for this plugin (Used to generate option names for saving licences to the DB).
	private $slug;

	// The current plugin version.
	private $version;

	// The current plugin's name.
	private $item_name;

	// The URL of the update server.
	private $update_server = 'https://wp-cart-recovery.com/';

	/**
	 * Constructor.
	 * Add actions to hook in at the appropriate places
	 * @param string $file      The main plugin file.
	 * @param string $slug      A unique slug for this plugin.
	 */
	public function __construct( $file, $slug ) {

		$this->file = $file;
		$this->slug = $slug;
		$this->version = $this->get_plugin_info( $this->file, 'Version' );
		$this->item_name = $this->get_plugin_info( $this->file, 'Name' );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'add_licence_admin_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $file ), array( $this, 'add_licence_admin_link' ) );

	}



	/**
	 * Run on admin_init.
	 * Loads licence details, and instantiates the main EDD SL plugin class.
	 */
	public function admin_init() {

		$licence = get_option( 'crfw_licence_' . $this->slug );

		$this->edd_sl_plugin_updater = new EDD_SL_Plugin_Updater(
			$this->update_server,
			$this->file,
			array(
				'version'   => $this->version,   // Current version number.
				'license'   => $licence,         // Licence key.
				'item_name' => $this->item_name, // Name of this plugin.
				'author'    => 'Lee Willis',     // Author of this plugin.
			)
		);

	}



	/**
	 * Add a "Enter Licence Key" link next to the plugin on the Plugins page.
	 *
	 * @param   array  $links  The existing plugin links.
	 * @return  array          The revised list of plugin links.
	 */
	public function add_licence_admin_link( $links ) {
		$settings_link = '<a href="options-general.php?page=crfw_licence_' . $this->slug . '">Enter licence key</a>';
		$links[] = $settings_link;
		return $links;
	}



	/**
	 * Make sure the enter licence key page is accessible even though it's not in the menu.
	 */
	public function add_licence_admin_menu() {
		global $_registered_pages;
		$hookname = get_plugin_page_hookname( 'crfw_licence_' . $this->slug, 'options-general.php' );
		if ( ! empty ( $hookname ) ) {
			add_action( $hookname, array( $this, 'licence_admin_page' ) );
		}
		$_registered_pages[ $hookname ] = true;
	}



	/**
	 * Check the licence, register it and save it.
	 */
	public function save_licence() {

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'licence_code_entry' ) ) {
			wp_die( 'Could not validate your request. Please go back and try again.', 'crfw_updater' );
		}

		$is_valid = $this->validate_licence( trim( $_POST['licence_code'] ) );

		if ( $is_valid ) {
			update_option( 'crfw_licence_' . $this->slug, trim( $_POST['licence_code'] ) );
			echo "<div id='message' class='updated'><p>Thank you, your licence has been validated and saved.</p></div>";
		} else {
			echo "<div id='message' class='error'><p>Sorry, but your licence cannot be validated. Either the licence is invalid, or is already in use on another site. If you believe this is an error please <a target='_blank' href='https://wp-cart-recovery.com/support/''>contact support</a>.</p></div>";
		}
	}



	/**
	 * Register the licence with the licencing server.
	 *
	 * @param  string  $licence  The licence code to be validated.
	 * @return bool              True if the licence is valid, false if not.
	 */
	public function validate_licence( $licence ) {

		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $licence,
			'item_name'  => urlencode( $this->item_name ),
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$this->update_server
			),
			array(
				'timeout' => 15,
				'sslverify' => false,
			)
		);

		// Make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Decode and save the licence data.
		$licence_data = json_decode( wp_remote_retrieve_body( $response ) );
		if ($licence_data->success == true && $licence_data->license == 'valid') {
			update_option( 'crfw_licence_status_' . $this->slug , $licence_data->license );
			return TRUE;
		}

		return FALSE;

	}



	/**
	 * De-register a licence from a site.
	 */
	public function remove_licence() {

		$licence = get_option( 'crfw_licence_' . $this->slug );

		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $licence,
			'item_name'  => urlencode( $this->item_name ),
		);

		// Call the custom API.
		$response = wp_remote_get(
			add_query_arg(
				$api_params,
				$this->update_server
			),
			array(
				'timeout' => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$licence_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 'deactivated' == $licence_data->license ) {
			delete_option( 'crfw_licence_status_' . $this->slug );
			delete_option( 'crfw_licence_' . $this->slug );
			echo "<div id='message' class='updated'><p>Thank you, your licence has been removed from this site.</p></div>";
			return true;
		}
		echo "<div id='message' class='error'><p>Sorry, your licence cannot be removed at this time. Please try again later.</p></div>";
		return false;

	}



	/**
	 * Show an admin page where the user can activate / deactivate their licence.
	 */
	public function licence_admin_page() {

		// Deal with form submissions.
		if ( isset( $_POST['licence_code'] ) ) {
			$this->save_licence();
		}

		// Deal with licence removals.
		if ( isset( $_POST['Remove_licence'] ) ) {
			$this->remove_licence();
		}

		$licence_code = get_option( 'crfw_licence_' . $this->slug );
		$licence_code = ! empty( $licence_code ) ? $licence_code : '';

		?>
		<div class="wrap">
		<h2>Licence Code Management</h2>
		<h4><?php echo esc_html( $this->get_plugin_info( $this->file, 'Name' ) ); ?></h4>
		<form method="post">
			<?php wp_nonce_field( 'licence_code_entry' ); ?>
			<p><label for="licence_code">Licence Code: </label><input type="text" size="40" name="licence_code" placeholder="Enter your licence code" value="<?php esc_attr_e( $licence_code ); ?>"></p>
			<p><input type="submit" class="button-primary" name="Save" value="Save"></p>
		</form>
		<?php if ( ! empty( $licence_code ) ) : ?>
		<form method="post">
			<p><input type="submit" class="button-secondary" name="Remove licence" value="Remove licence"></p>
		</form>
		<?php endif; ?>
		<?php

	}


	/**
	 * Get the version of a plugin given it's main file.
	 *
	 * @param  string  $file  The main plugin file
	 * @return string         The version number of the plugin
	 */
	private function get_plugin_info( $file, $key ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( $file ) ) );
		$plugin_file = basename( ( $file ) );
		return $plugin_folder[ $plugin_file ][ $key ];
	}

}
