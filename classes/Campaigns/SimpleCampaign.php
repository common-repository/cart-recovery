<?php

namespace Ademti\Crfw\Campaigns;

use Ademti\Crfw\Settings;
use Ademti\Crfw\Cart;
use Ademti\Crfw\CartEvent;
use Ademti\Crfw\CartTemplate;
use Ademti\Crfw\Campaigns\AbstractCampaign;
use function time;

class SimpleCampaign extends AbstractCampaign {
	/**
	 * @var int
	 */
	public int $unrecovered_timeout;

	/**
	 * Initialise.
	 *
	 * Set the slug and label.
	 */
	public function init() {
		$this->slug  = 'crfw_simple';
		$this->label = __( 'Simple campaign', 'cart-recovery' );
		// Set unrecovered timeout to 2 days.
		$this->unrecovered_timeout = apply_filters( 'crfw_simple_campaign_unrecovered_timeout', 172800 );
		add_filter( 'crfw_settings_tabs', array( $this, 'settings_tabs' ) );
		add_action( 'crfw_settings_form', array( $this, 'settings_form' ) );
	}

	/**
	 * Register our settings tab.
	 *
	 * @param array $tabs Array of tab slugs and labels.
	 *
	 * @return array        Modified array of tab slugs and labels.
	 */
	public function settings_tabs( $tabs ) {
		$tabs['email'] = array(
			'label'    => __( 'Simple campaign', 'cart-recovery' ),
			'callback' => array( $this, 'settings_page' ),
		);

		return $tabs;
	}

	/**
	 * Render the list of email tags available.
	 */
	public function email_tag_list_render() {
		$cart_template = new CartTemplate( new Cart(), $this->settings, $this->settings->crfw_email_subject );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cart_template->get_email_tag_list();
	}

	/**
	 * Register our settings.
	 */
	public function settings_form() {
		// Email settings tab.
		register_setting( 'crfw_email_plugin_page', 'crfw_settings_email' );
		add_settings_section(
			'crfw_email_section',
			'',
			null,
			'crfw_email_plugin_page'
		);
		add_settings_field(
			'crfw_email_subject',
			__( 'Email subject line', 'cart-recovery' ),
			array( $this, 'email_subject_render' ),
			'crfw_email_plugin_page',
			'crfw_email_section'
		);
		add_settings_field(
			'crfw_email_content',
			__( 'Email content', 'cart-recovery' ),
			array( $this, 'email_content_render' ),
			'crfw_email_plugin_page',
			'crfw_email_section'
		);
		add_settings_field(
			'crfw_email_tag_list',
			__( 'Email tags', 'crfw' ),
			array( $this, 'email_tag_list_render' ),
			'crfw_email_plugin_page',
			'crfw_email_section'
		);
	}

	/**
	 * Render the settings page for the Simple Campaign.
	 */
	public function settings_page() {
		settings_fields( 'crfw_email_plugin_page' );
		do_settings_sections( 'crfw_email_plugin_page' );
		do_action( 'crfw_email_plugin_page' );
		submit_button();
	}

	/**
	 * Render the email subject input box.
	 */
	public function email_subject_render() {
		?>
		<input type='text' name='crfw_settings_email[crfw_email_subject]' size="60"
				value='<?php echo esc_attr( $this->settings->crfw_email_subject ); ?>'>
		<?php
	}

	/**
	 * Render the textarea editor for the email content input.
	 */
	public function email_content_render() {
		wp_editor(
			$this->settings->crfw_email_content,
			'crfw_settings_email',
			array(
				'textarea_name' => 'crfw_settings_email[crfw_email_content]',
			)
		);
	}

	/**
	 * Run the campaign.
	 */
	public function run_campaign() {
		$this->mark_carts_as_unrecovered();
		$cart_ids = $this->get_carts_to_email();
		foreach ( $cart_ids as $cart_id ) {
			$this->send_campaign( $cart_id );
		}
	}

	/**
	 * Get the carts to email.
	 *
	 * @return array  Array of cart IDs.
	 */
	private function get_carts_to_email() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id
                       FROM %i c
                  LEFT JOIN %i m
                         ON c.id = m.cart_id
                        AND m.name = 'recovery_started'
                  LEFT JOIN %i m2
                         ON c.id = m2.cart_id
                        AND m2.name = 'simple_campaign_sent'
                      WHERE c.status = 'recovery'
                        AND m2.name IS NULL
                        AND m.value < %d
                       ",
				$wpdb->prefix . 'crfw_cart',
				$wpdb->prefix . 'crfw_cart_meta',
				$wpdb->prefix . 'crfw_cart_meta',
				time() - 1800
			)
		);
	}


	/**
	 * Get carts that have passed the campaign time and unrecovered threshold.
	 *
	 * @return array  Array of cart IDs.
	 */
	private function get_unrecovered_carts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id
				   FROM %i c
		      LEFT JOIN %i m
		             ON c.id = m.cart_id
		            AND m.name = 'recovery_started'
		          WHERE c.status = 'recovery'
		            AND m.value < %d
				   ",
				$wpdb->prefix . 'crfw_cart',
				$wpdb->prefix . 'crfw_cart_meta',
				time() - 1800 - $this->unrecovered_timeout
			)
		);
	}

	/**
	 * Mark carts as unrecovered.
	 */
	private function mark_carts_as_unrecovered() {
		$cart_ids = $this->get_unrecovered_carts();
		foreach ( $cart_ids as $cart_id ) {
			$cart         = new Cart( $cart_id );
			$cart->status = 'unrecovered';
			$cart->save();
			// Log event.
			$event          = new CartEvent();
			$event->cart_id = $cart->cart_id;
			$event->type    = 'negative';
			$event->details = __( 'Cart marked as unrecovered.', 'cart-recovery' );
			$event->save();
		}
	}

	/**
	 * Send the email about a specific cart.
	 *
	 * @param int $cart_id The cart ID.
	 */
	private function send_campaign( $cart_id ) {
		// Check if we're enabled. If not - we're done.
		if ( ! $this->settings->crfw_recover_checkout_emails ) {
			return;
		}

		$cart = new Cart( $cart_id );
		if ( ! apply_filters( 'crfw_send_campaign', true, $cart, $this->slug ) ) {
			// Mark as sent so it doesn't get attempted again.
			$cart->add_meta( 'simple_campaign_sent', time() );
			$cart->save();
			// Log event.
			$event          = new CartEvent();
			$event->cart_id = $cart_id;
			$event->type    = 'negative';
			$event->details = __( 'Simple campaign NOT sent. Blocked by filter.', 'cart-recovery' );
			$event->save();

			return;
		}

		$cart_template = new CartTemplate( $cart, $this->settings, $this->settings->crfw_email_subject );
		$msg           = $this->settings->crfw_email_content;
		$msg           = wpautop( $cart_template->replace( $msg, 'body' ) );
		$sent          = $this->settings->engine->mail(
			$cart->email,
			$cart_template->replace( $this->settings->crfw_email_subject, 'title' ),
			$msg
		);
		if ( $sent ) {
			// Update the meta.
			$cart->add_meta( 'simple_campaign_sent', time() );
			$cart->save();
			// Log event.
			$event          = new CartEvent();
			$event->cart_id = $cart_id;
			$event->type    = 'neutral';
			$event->details = __( 'Simple campaign sent.', 'cart-recovery' );
			$event->save();
		} else {
			// Log event.
			$event          = new CartEvent();
			$event->cart_id = $cart_id;
			$event->type    = 'neutral';
			$event->details = __( 'Simple campaign send attempted, but could not be sent.', 'cart-recovery' );
			$event->save();

			// Track the failed attempt to send.
			$attempts = $cart->get_meta( 'simple_campaign_attempts' );
			if ( is_null( $attempts ) ) {
				$attempts = 0;
			}
			$cart->update_meta( 'simple_campaign_attempts', ++$attempts );
			if ( $attempts >= 3 ) {
				// Mark it as sent so we do not try and send it again.
				$cart->add_meta( 'simple_campaign_sent', time() );
				// Log a descriptive event.
				$event          = new CartEvent();
				$event->cart_id = $cart_id;
				$event->type    = 'negative';
				$event->details = __( 'Simple campaign attempted 3 or more times. Will not attempt again.', 'cart-recovery' );
				$event->save();
			}
			$cart->save();
		}
	}
}
