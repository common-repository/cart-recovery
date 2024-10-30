<?php

namespace Ademti\Crfw;

use Ademti\Crfw\TemplateLoader;
use function disabled;

class SettingsForm {

	/**
	 * Constructor - store the settings class.
	 */
	public function __construct( $settings ) {
		$this->settings        = $settings;
		$this->template_loader = new TemplateLoader();
	}

	/**
	 * Settings callback.
	 *
	 * Hooked onto admin_init by Settings::__construct.
	 *
	 * @return void
	 * @see  Settings::__construct
	 *
	 */
	public function settings_init() {

		// Main settings tab.
		register_setting( 'crfw_main_plugin_page', 'crfw_settings_main' );

		add_settings_section(
			'crfw_main_section',
			'',
			null,
			'crfw_main_plugin_page'
		);

		add_settings_field(
			'crfw_recover_checkout_emails',
			__( 'Send campaigns', 'cart-recovery' ),
			array( $this, 'recover_checkout_emails_render' ),
			'crfw_main_plugin_page',
			'crfw_main_section'
		);
		add_settings_field(
			'crfw_email_from',
			__( 'Email "From" name', 'cart-recovery' ),
			array( $this, 'email_from_render' ),
			'crfw_main_plugin_page',
			'crfw_main_section'
		);
		add_settings_field(
			'crfw_email_from_address',
			__( 'Email "From" address', 'cart-recovery' ),
			array( $this, 'email_from_address_render' ),
			'crfw_main_plugin_page',
			'crfw_main_section'
		);
		add_settings_field(
			'crfw_anonymise_period',
			__( 'Anonymise old cart records', 'cart-recovery' ),
			array( $this, 'anonymise_period_render' ),
			'crfw_main_plugin_page',
			'crfw_main_section'
		);
		do_action( 'crfw_settings_form' );
	}

	public function recover_checkout_emails_render() {
		?>
		<input type='checkbox' <?php disabled( ! get_option( 'crfw_cart_completion_working' ) ); ?> name='crfw_settings_main[crfw_recover_checkout_emails]'
			<?php checked( $this->settings->crfw_recover_checkout_emails, 1 ); ?> value='1'>
		<?php
		if ( ! get_option( 'crfw_cart_completion_working' ) ) {
			$this->template_loader->output_template_with_variables( 'admin', 'enablement-notice', array() );
		}
	}

	/**
	 * Render the email from input box.
	 */
	public function email_from_render() {
		?>
		<input type='text' name='crfw_settings_main[crfw_email_from]' size="40"
				value='<?php echo esc_attr( $this->settings->crfw_email_from ); ?>'>
		<?php
	}

	/**
	 * Render the email from input box.
	 */
	public function email_from_address_render() {
		?>
		<input type='text' name='crfw_settings_main[crfw_email_from_address]' size="40"
				value='<?php echo esc_attr( $this->settings->crfw_email_from_address ); ?>'>
		<?php
	}

	public function anonymise_period_render() {
		$anonymisation_periods = apply_filters(
			'crfw_anonymisation_periods',
			[
				''        => __( 'Never', 'cart-recovery' ),
				'432000'  => __( 'After 5 days', 'cart-recovery' ),
				'604800'  => __( 'After 7 days', 'cart-recovery' ),
				'1209600' => __( 'After 14 days', 'cart-recovery' ),
				'2419200' => __( 'After 28 days', 'cart-recovery' ),
			]
		);
		echo "<select name='crfw_settings_main[crfw_anonymise_period]'>";
		foreach ( $anonymisation_periods as $period => $description ) {
			$current_period = $this->settings->crfw_anonymise_period;
			echo '<option ' . selected( $period, $current_period, false ) . ' value="' . esc_attr( $period ) . '">' . esc_html( $description ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Help text for the main settings page.
	 */
	public function help_main() {
		echo wp_kses(
			__( "<p>On this page you can choose to enable abandoned cart tracking, or not.</p><p><strong>Note:</strong> - if you disable tracking, then no further emails will be sent - even for carts already in the recovery process.</p><p>The available tracking is based on email entry during checkout. When a customer enters their email during checkout then it is stored, together with details of the customer's baseket contents. If the customer doesn't successfully complete an order then they will enter the recovery process.</p>", 'cart-recovery' ),
			[
				'p'      => [],
				'strong' => [],
			]
		);
	}

	/**
	 * Help text for the email settings page.
	 */
	public function help_email() {
		echo wp_kses( __( "<p>On this page you can enter the email details that you want to send to customers who don't complete their order. You can specify the From name, and email address, the subject line, and the email content.", 'cart-recovery' ), [ 'p' => [] ] );
	}
}
