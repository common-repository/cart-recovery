<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Settings;
use Ademti\Crfw\TemplateLoader;

/**
 * Notification email class.
 *
 * Used to send notification emails to the site admin when a cart is recovered.
 */
class RecoveredCartNotificationEmails {

	/**
	 * @var \Ademti\Crfw\TemplateLoader
	 */
	private $template_loader;

	/**
	 * @var \Ademti\Crfw\Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * Store all data for future use.
	 *
	 * @param Settings $settings The general settings object.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Run the class features.
	 */
	public function run() {
		$this->template_loader = new TemplateLoader();
		add_action( 'crfw_after_complete_cart', array( $this, 'after_complete_cart' ) );
	}

	/**
	 * Triggered when a cart completes (normally, or via recovery)
	 *
	 * @param Cart $cart
	 */
	public function after_complete_cart( Cart $cart ) {
		// Do nothing unless the cart was recovered.
		if ( 'recovered' !== $cart->status ) {
			return;
		}
		// Allow disabling of notifications.
		if ( ! apply_filters( 'crfw_send_recovered_cart_notifications', true ) ) {
			return;
		}

		// Parse the details into the notification template.
		$cart_template = new CartTemplate( $cart, $this->settings );
		$value         = $this->settings->engine->format_price(
			$cart->get_meta( 'order_value' ),
			$cart->cart_details['currency']
		);
		$subject       = sprintf(
			// Translators: %s is a formatted financial amount.
			__( '%s successfully recovered', 'cart-recovery' ),
			html_entity_decode( wp_strip_all_tags( $value ) )
		);
		$cart_table = $cart_template->replace( '{cart}' );

		$message = $this->template_loader->get_template_with_variables(
			'admin',
			'recovered-cart-notification-email',
			array(
				'subject'    => $subject,
				'value'      => $value,
				'cart_table' => $cart_table,
			)
		);
		// Wrap the notification email content in the standard wrapper.
		$message = $this->template_loader->get_template_with_variables(
			'email',
			'html-wrapper',
			array(
				'subject' => $subject,
				'message' => $message,
			)
		);

		// Send it to the "From" campaign address by default, overridable by filter.
		$recipient_address = apply_filters(
			'crfw_recovered_cart_notification_address',
			$this->settings->crfw_email_from_address
		);

		// Send the notification.
		$this->settings->engine->mail(
			$recipient_address,
			$subject,
			$message
		);
	}
}
