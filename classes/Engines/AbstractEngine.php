<?php

namespace Ademti\Crfw\Engines;

use Ademti\Crfw\Settings;
use Ademti\Crfw\Cart;
use Ademti\Crfw\CartEvent;
use Ademti\Crfw\TemplateLoader;
use Exception;
use function sanitize_key;
use function sanitize_text_field;

abstract class AbstractEngine {

	protected $settings;
	protected $suffix;

	/**
	 * Constructor.
	 *
	 * Store the base URL, and add hooks.
	 *
	 * @param Settings $settings
	 */
	public function __construct( Settings $settings ) {
		// Store the settings.
		$this->settings = $settings;

		// Enqueue the frontend javascript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register the AJAX callback handler.
		add_action( 'wp_ajax_nopriv_crfw_record_cart', array( $this, 'ajax_record_cart' ) );
		add_action( 'wp_ajax_crfw_record_cart', array( $this, 'ajax_record_cart' ) );

		// Register the order completed action.
		$this->register_order_completed_action();

		// Register any actions required to keep cart updated.
		$this->register_cart_update_hooks();

		// Init. Allow cart regeneration.
		add_action( 'init', array( $this, 'init' ), 20 );
	}

	/**
	 * See if we need to re-populate the cart.
	 */
	public function init() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		// Bail if get arg not present.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crfw_cart_hash'] ) ) {
			return;
		}

		// Validate the hash.
		$cart = new Cart();
		$hash = $cart->load_by_hash( wp_unslash( $_GET['crfw_cart_hash'] ) );

		// If we could not find it - bail.
		if ( $hash !== $_GET['crfw_cart_hash'] ) {
			return;
		}
		// Validate that the email matches. Bail if not.
		$crfw_email = sanitize_email( wp_unslash( $_GET['crfw_email'] ?? '' ) );
		if ( $cart->email !== $crfw_email ) {
			return;
		}

		// Re-populate / freshen the cookie.
		setcookie( 'crfw_cart_hash', $hash, time() + 86400, COOKIEPATH );

		// Do something
		$crfw_action = isset( $_GET['crfw_action'] ) ?
			sanitize_key( wp_unslash( $_GET['crfw_action'] ) ) :
			null;

		switch ( $crfw_action ) {
			case 'checkout':
				// Repopulate the cart.
				do_action( 'crfw_before_repopulate_cart', $cart );
				$this->log_cart_clickthrough( $cart->cart_id );
				$this->repopulate_cart( $cart );
				break;
			case 'unsubscribe':
				// Unsubscribe the cart from recovery.
				$cart->unsubscribe();
				$cart->save();
				do_action( 'crfw_after_unsubscribe_cart', $cart );
				add_action( 'wp_footer', array( $this, 'include_unsubscribe_message_template' ) );
				break;
			default:
				// Do nothing.
		}
		// phpcs:enable
	}

	/**
	 * Show unsubscribe confirmation.
	 */
	public function include_unsubscribe_message_template() {
		wp_enqueue_style(
			'crfw-remodal',
			$this->settings->base_url . '/css/remodal.css',
			array(),
			CRFW_VERSION
		);
		wp_enqueue_style(
			'crfw-remodal-theme',
			$this->settings->base_url . '/css/remodal-default-theme.css',
			array(),
			CRFW_VERSION
		);
		wp_enqueue_script(
			'',
			$this->settings->base_url . '/js/remodal.min.js',
			array( 'jquery' ),
			CRFW_VERSION,
			true
		);
		$template_loader = new TemplateLoader();
		$template_loader->output_template_with_variables( 'frontend', 'unsubscribe-message', array() );
	}

	/**
	 * Add the JS to the page if needed.
	 */
	public function enqueue_scripts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $this->is_checkout() && empty( $_GET['crfw_action'] ) ) {
			return;
		}
		$suffix = $this->get_suffix();
		$min    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script(
			'crfw-' . $suffix,
			$this->settings->base_url . "/js/crfw-{$suffix}{$min}.js",
			array( 'crfw' ),
			CRFW_VERSION,
			true
		);
	}

	/**
	 * Update a cart record to indicate it was completed.
	 *
	 * Also clears the cart cookie.
	 *
	 * @param  string  $email  The email associated with the order, used as a fallback if there is
	 *                       no hash (E.g. because order is being completed by a payment gateway
	 *                       callback.)
	 *
	 * @throws Exception
	 */
	public function set_cart_completed( $email = null, $order_value = '' ) {
		// See if we have a cart hash. If so, load the cart object from the hash.
		$cart_hash = isset( $_COOKIE['crfw_cart_hash'] ) ?
			wp_unslash( $_COOKIE['crfw_cart_hash'] ) :
			null;
		if ( null !== $cart_hash ) {
			$cart = new Cart();
			$cart->load_by_hash( $cart_hash );
			// Clear the cookie.
			setcookie( 'crfw_cart_hash', null, time() - 10, COOKIEPATH );
			// If we found one, update its status.
			if ( ! empty( $cart->cart_id ) ) {
				$old_status = $cart->status;
				$cart->set_completed( $order_value );
				if ( $cart->status !== $old_status ) {
					$cart->save();
					// Log event.
					$event          = new CartEvent();
					$event->cart_id = $cart->cart_id;
					$event->type    = 'positive';
					$event->details = __( 'Cart completed.', 'cart-recovery' );
					$event->save();
					do_action( 'crfw_after_complete_cart', $cart );
				}
			}
		}
		// Also set any carts that match this email address as completed.
		while ( true ) {
			$cart = new Cart();
			$cart->load_by_email( $email );
			if ( empty( $cart->cart_id ) ) {
				break;
			}
			$old_status = $cart->status;
			// We found one, update the cart status.
			$cart->set_completed( $order_value );
			if ( $cart->status !== $old_status ) {
				$cart->save();
				// Log event.
				$event          = new CartEvent();
				$event->cart_id = $cart->cart_id;
				$event->type    = 'positive';
				$event->details = __( 'Cart completed.', 'cart-recovery' );
				$event->save();
				do_action( 'crfw_after_complete_cart', $cart );
			}
		}
	}

	/**
	 * Log cart clickthrough.
	 */
	protected function log_cart_clickthrough( $cart_id ) {
		// Allow other code to block the clickthrough logging.
		if ( ! apply_filters( 'crfw_log_cart_clickthrough', true, $cart_id ) ) {
			return;
		}
		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $cart_id;
		$event->type    = 'positive';
		$event->details = __( 'User clicked back to their cart.', 'cart-recovery' );
		$event->save();
	}

	/**
	 * Find / create a cart record.
	 *
	 * This will try and match the cart by hash initially. If not found, it will look for an
	 * existing pending cart for this email address. If not found, it will be a new cart record.
	 *
	 * @return Cart  The cart object.
	 */
	private function find_cart( $email ) {

		$cart = new Cart();
		// See if we have a hash.
		$cart_hash = isset( $_COOKIE['crfw_cart_hash'] ) ?
			wp_unslash( $_COOKIE['crfw_cart_hash'] ) :
			null;
		// If so, load the cart object from the hash.
		if ( null !== $cart_hash ) {
			$cart->load_by_hash( $cart_hash );
		}
		// Check that the cart isn't already recovered / completed.
		// If so, start a new one.
		if ( in_array( $cart->status, array( 'recovered', 'unrecovered', 'completed' ), true ) ) {
			$cart = new Cart();
		}
		// If we still have no valid cart record, try and load from the email address.
		if ( empty( $cart->cart_id ) ) {
			// If we still have no valid cart record, try and load from the email address.
			$cart->load_by_email( $email );
		}

		return $cart;
	}

	/**
	 * AJAX handler for the record_cart action called from the checkout page.
	 */
	public function ajax_record_cart() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$surname    = sanitize_text_field( wp_unslash( $_POST['surname'] ?? '' ) );
		// phpcs:enable
		$cart_details = $this->settings->engine->get_cart_details();
		$this->record_cart( $email, $first_name, $surname, $cart_details, true );
	}

	/**
	 * Called to refresh the cart details.
	 */
	public function update_cart() {
		$cart_details = $this->settings->engine->get_cart_details();
		$user_details = $this->settings->engine->get_user_details();
		$this->record_cart(
			$user_details['email'],
			$user_details['first_name'],
			$user_details['surname'],
			$cart_details,
			false
		);
	}

	/**
	 * Whether the customer has a recent cart record in any "completed"-type status.
	 *
	 * @param $email
	 *
	 * @return bool
	 */
	private function has_recent_completed_cart( $email ) {
		global $wpdb;
		// Look for a cart for this email in the right status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
						  FROM %i
						 WHERE email = %s
						   AND updated > %d
						   AND status in ( 'completed', 'recovered' )
						 LIMIT 1",
				[ $wpdb->prefix . 'crfw_cart', $email, time() - 3600 ]
			)
		);

		return ! empty( $cart_id );
	}

	/**
	 * Store the cart in the database.
	 *
	 * @param string $email Customer's email, may be blank.
	 * @param string $first_name Customer's first name, may be blank.
	 * @param string $surname Customer's surname, may be blank.
	 * @param array $cart_details The cart details.
	 * @param bool $update_user_details Whether to overwrite the cart
	 *                                      user-details or not. If there are no
	 *                                      user details on the cart record, the
	 *                                      ones passed in will still be used.
	 */
	public function record_cart( $email, $first_name, $surname, $cart_details, $update_user_details = true ) {
		if ( $this->has_recent_completed_cart( $email ) ) {
			// Do nothing if they have a recently completed cart.
			// Avoids issues where fresh cart records are created when carts aren't cleared as soon as they are
			// "completed" - e.g. offsite payment with IPN-style callbacks later.
			return;
		}

		// Allow 3rd parties to block carts from being recorded.
		if ( ! apply_filters( 'crfw_should_record_cart', true, $email, $cart_details ) ) {
			return;
		}

		$cart = $this->find_cart( $email );

		// Do not log new empty cart records.
		// Works around issues where the WooCommerce update cart hook is called
		// by internal code even though nothing has been put into the cart.
		if ( empty( $cart->cart_id ) && empty( $cart_details ) ) {
			return;
		}

		// Populate the data.
		if ( ! empty( $email ) ) {
			if ( empty( $cart->email ) || $update_user_details ) {
				$cart->email = $email;
			}
		}
		// Validate the email address, bail if it doesn't pass.
		if ( ! filter_var( $cart->email, FILTER_VALIDATE_EMAIL ) ) {
			return;
		}
		if ( ! empty( $first_name ) && ! empty( $surname ) ) {
			if ( ( empty( $cart->first_name ) && empty( $cart->surname ) ) ||
				$update_user_details ) {
				$cart->first_name = $first_name;
				$cart->surname    = $surname;
			}
		}
		$cart->cart_details = $cart_details;

		if ( empty( $cart_details ) && 'pending' === $cart->status ) {
			$cart->status = 'empty';
		} elseif ( empty( $cart_details ) && 'recovery' === $cart->status ) {
			$cart->unrecovered( 'cart_unrecovered', __( 'User emptied cart, recovery abandoned.', 'cart-recovery' ) );
		} elseif ( ! empty( $cart_details ) && 'empty' === $cart->status ) {
			$cart->status = 'pending';
		}

		// We only update the cart update time while it is "pending", or "empty"
		if ( 'pending' === $cart->status || 'empty' === $cart->status ) {
			$cart->updated = time();
		}

		$cart_hash = $cart->save();

		// Store the logged in user ID in cart meta (may be 0)
		$user = wp_get_current_user();
		$cart->update_meta( 'user_id', $user->ID );

		do_action( 'crfw_after_record_cart', $cart );

		// Store the hash back into the cookie / freshen the existing cookie.

		// Note: WooCommerce sometimes its cart updated hook mid-page even when
		// no changes have been made. So - we avoid "headers sent" warnings by
		// not setting the cookie if we're mid-page. It should already have been
		// set anyway by previous call.
		if ( ! headers_sent() ) {
			setcookie( 'crfw_cart_hash', $cart_hash, time() + 86400, COOKIEPATH );
		}
	}

	/**
	 * Send an email.
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	public function mail( $to, $subject, $message ) {
		$template_loader = new TemplateLoader();
		$variables       = array(
			'message' => $message,
			'subject' => $subject,
		);
		$message         = $template_loader->get_template_with_variables( 'email', 'html-wrapper', $variables );

		add_filter( 'wp_mail_from', array( $this, 'wp_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'wp_mail_from_name' ) );
		$res = wp_mail( $to, $subject, $message, array( 'Content-type: text/html' ) );
		remove_filter( 'wp_mail_from', array( $this, 'wp_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'wp_mail_from_name' ) );

		return $res;
	}

	/**
	 * Override email address used by wp_mail().
	 *
	 * @param string $email The email address to be used by wp_mail() by default.
	 *
	 * @return string        The email address to use for the campaign.
	 */
	public function wp_mail_from( $email ) {
		if ( ! empty( $this->settings->crfw_email_from_address ) ) {
			return $this->settings->crfw_email_from_address;
		}

		return $email;
	}

	/**
	 * Override email from name used by wp_mail().
	 *
	 * @param string $name The default name used by wp_mail().
	 *
	 * @return string       The name to be used for the campaign.
	 */
	public function wp_mail_from_name( $name ) {
		if ( ! empty( $this->settings->crfw_email_from ) ) {
			return $this->settings->crfw_email_from;
		}

		return $name;
	}

	/**
	 * Get the cart details in a standard format.
	 */
	abstract protected function get_cart_details();

	/**
	 * Get the details of the current user.
	 *
	 * @return array  Array of the users email, first name and surname.
	 */
	abstract protected function get_user_details();

	/**
	 * Get the items in the cart.
	 */
	abstract protected function get_cart_items( $cart_details );

	/**
	 * Generate a formatted price string with currency.
	 *
	 * @param $price
	 * @param $currency
	 *
	 * @return string
	 */
	abstract public function format_price( $price, $currency );

	/**
	 * Generate a string describing the value of a cart.
	 */
	abstract public function get_cart_value( $cart_details );

	/**
	 * Repopulate the cart based on a hasehed cart ID in the $_GET vars.
	 */
	abstract public function repopulate_cart( Cart $cart );

	/**
	 * Get the checkout URL.
	 * @return string  The URL.
	 */
	abstract public function get_checkout_url();

	/**
	 * Allow plugins to request sample content for this engine for creating
	 * sample carts.
	 */
	abstract public function get_sample_cart_details();

	/**
	 * Format a numeric amount based on the store currency.
	 */
	abstract public function currency_format( $amount );

	/**
	 * Return the suffix for the engine.
	 *
	 * @return string The suffix.
	 */
	abstract protected function get_suffix();

	/**
	 * Determine if the current page is a checkout page.
	 *
	 * @return boolean True if the current page is the checkout page.
	 */
	abstract protected function is_checkout();

	/**
	 * Add a hook to trigger the order completed action.
	 */
	abstract protected function register_order_completed_action();

	/**
	 * Registers any hooks needed to keep the cart information up-to-date.
	 */
	abstract protected function register_cart_update_hooks();
}
