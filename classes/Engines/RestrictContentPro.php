<?php

namespace Ademti\Crfw\Engines;

use Ademti\Crfw\Engines\AbstractEngine;
use Ademti\Crfw\Settings;
use Ademti\Crfw\Cart;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_url;
use function wp_unslash;

/**
 * RestrictContentPro Frontend Engine.
 */
class RestrictContentPro extends AbstractEngine {

	/**
	 * Make sure our JS-additions are hooked.
	 */
	public function __construct( Settings $settings ) {
		parent::__construct( $settings );
		add_filter( 'crfw_js_info', array( $this, 'add_info_to_js' ) );
	}

	/**
	 * Add the current URL to the on-page JS settings object.
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	public function add_info_to_js( $js_info ) {
		global $wp;
		$js_info['registration_path'] = $wp->request;

		return $js_info;
	}

	/**
	 * Get the current cart details in a standard format.
	 */
	public function get_cart_details() {
		$details = [
			'currency'        => rcp_get_currency(),
			'currency_symbol' => $this->rcp_get_currency_symbol(),
			'contents'        => [],
		];

		$details['registration_path'] = sanitize_url(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_unslash( $_POST['extra_info']['registration_path'] ?? '' )
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$subscription = sanitize_key( wp_unslash( $_POST['extra_info']['rcp_subscription_id'] ?? '' ) );
		if ( ! $subscription ) {
			return $details;
		}
		$subscription          = $this->rcp_get_level( $subscription );
		$details['contents'][] = apply_filters(
			'crfw_cart_item',
			array(
				'name'     => $subscription->name,
				'price'    => $subscription->price,
				'image'    => '',
				'quantity' => 1,
				'internal' => $subscription,
			),
			$subscription,
			0
		);

		return $details;
	}

	/**
	 * @param $subscription_id
	 *
	 * @return false|\RCP\Membership_Level
	 */
	private function rcp_get_level( $subscription_id ) {
		global $rcp_levels_db;
		if ( is_callable( 'rcp_get_membership_level' ) ) {
			return rcp_get_membership_level( $subscription_id );
		}

		return $rcp_levels_db->get_level( $subscription_id );
	}

	/**
	 * Get the details of the current user.
	 *
	 * @return array  Array of the users email, first name and surname.
	 */
	protected function get_user_details() {
		// Empty by default.
		$details = array(
			'email'      => '',
			'first_name' => '',
			'surname'    => '',
		);
		// Grab the current WP user. Bail if there isn't one.
		$user = wp_get_current_user();
		if ( empty( $user->ID ) ) {
			return $details;
		}
		// Use the details from the user record.
		$details['email']      = $user->user_email;
		$names                 = explode( ' ', $user->nicename );
		$details['surname']    = array_pop( $names );
		$details['first_name'] = implode( ' ', $names );

		return $details;
	}

	/**
	 * Repopulate the cart.
	 *
	 * Also handles redirecting to the correct registration page.
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	public function repopulate_cart( Cart $cart ) {

		global $wp;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$crfw_redirected = sanitize_key( wp_unslash( $_GET['crfw_redirected'] ?? '' ) );
		// First - check if we're on the correct registration page. If not,
		// redirect preserving the relevant query args.
		if ( $wp->request !== $cart->cart_details['registration_path'] &&
			empty( $crfw_redirected ) ) {
			// Generate the base URL.
			$redirect_url = home_url( $cart->cart_details['registration_path'] );
			// Add the info we need. Note: We add crfw_redirected to protect
			// against redirect loops, although it shouldn't ever be needed.
			$args = array(
				'crfw_cart_hash'  => $cart->hashed_id,
				'crfw_email'      => rawurlencode( $cart->email ),
				'crfw_action'     => 'checkout',
				'crfw_redirected' => 1,
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$crfw_discount = sanitize_text_field( wp_unslash( $_GET['crfw_discount'] ?? null ) );
			if ( ! empty( $crfw_discount ) ) {
				$args['crfw_discount'] = $crfw_discount;
			}
			$redirect_url = add_query_arg(
				$args,
				$redirect_url
			);
			wp_safe_redirect(
				apply_filters( 'crfw_return_redirect_url', $redirect_url, $cart ),
				'303'
			);
			die();
		}

		// We fake the POST/GET vars to make RCP think the data has come from
		// a submission and populate the form.
		$_POST['rcp_user_email'] = $cart->email;
		$_POST['rcp_user_first'] = $cart->first_name;
		$_POST['rcp_user_last']  = $cart->surname;
		$_GET['level']           = $cart->cart_details['contents'][0]['internal']->id;

		do_action( 'crfw_after_repopulate_cart', $cart );
	}

	/**
	 * Get the items in the cart for display in the admin area.
	 *
	 * Based on the cart details array, return an array keyed on
	 * product name with the value being the quantity, e.g.
	 *
	 * array(
	 *     'Red widget' => 1,
	 *     'Blue widget' => 5,
	 * )
	 *
	 * @param array $cart_details The cart details stored.
	 *
	 * @return array                Array of product names and their counts.
	 */
	public function get_cart_items( $cart_details ) {
		if ( empty( $cart_details['contents'] ) ) {
			return array();
		}
		$results = array();
		foreach ( $cart_details['contents'] as $item ) {
			$results[ $item['name'] ] = $item['quantity'];
		}

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	public function format_price( $price, $currency ) {
		return rcp_currency_filter( sprintf( '%2.2f', $price ) );
	}

	/**
	 * Generate a string describing the value of a cart.
	 */
	public function get_cart_value( $cart_details ) {
		if ( empty( $cart_details['contents'] ) ) {
			return $this->format_price( 0, null );
		}
		$total = 0;
		foreach ( $cart_details['contents'] as $item ) {
			$total += $item['price'];
		}

		return $this->format_price( $total, null );
	}

	/**
	 * Get the checkout URL.
	 *
	 * Note: Because RCP can have multiple registration pages, we treat '/' as
	 * the checkout. This means that email links go to the home page, which will
	 * then grab the info from the cart, and redirect to  the relevant
	 * registration page.
	 *
	 * See also repopulate_cart() where the redirection happens.
	 *
	 * @return string  The checkout URL.
	 */
	public function get_checkout_url() {
		return home_url();
	}

	/**
	 * Compatibility wrapper for older versions of RCP.
	 *
	 * Uses rcp_get_currency_symbol() if available, otherwise gets the currency
	 * symbol via a horrible str_replace hack.
	 */
	private function rcp_get_currency_symbol() {
		if ( is_callable( 'rcp_get_currency_symbol' ) ) {
			return rcp_get_currency_symbol();
		} else {
			return str_replace( '0.00', '', rcp_currency_filter( '0.00' ) );
		}
	}

	/*
	 * Generate a sample cart details array.
	 */
	public function get_sample_cart_details() {
		$data = array(
			'currency'        => rcp_get_currency(),
			'currency_symbol' => $this->rcp_get_currency_symbol(),
			'contents'        => array(),
		);
		// Pick some sample products to use.
		$subscription = $this->get_sample_subscription();

		// Generate the details array in the correct format.
		$item               = array(
			'name'     => $subscription->name,
			'price'    => $subscription->price,
			'image'    => '',
			'quantity' => 1,
			'internal' => $subscription,
		);
		$data['contents'][] = $item;

		return $data;
	}

	/**
	 * Format a numeric amount based on the store currency.
	 */
	public function currency_format( $amount ) {
		return rcp_currency_filter( sprintf( '%.2f', $amount ) );
	}

	/**
	 * Find a subscription we can use in the sample content.
	 */
	private function get_sample_subscription() {
		global $rcp_levels_db;

		$args = [
			'status' => 'active',
		];
		if ( is_callable( 'rcp_get_membership_levels' ) ) {
			$subscriptions = rcp_get_membership_levels( $args );
		} else {
			$subscriptions = $rcp_levels_db->get_levels( $args );
		}

		shuffle( $subscriptions );

		return array_pop( $subscriptions );
	}

	/**
	 * Return the suffix for the engine.
	 *
	 * @return string The suffix.
	 */
	protected function get_suffix() {
		return 'rcp';
	}

	/**
	 * Determine if the current page is a checkout page.
	 *
	 * @return boolean True if the current page is a registration page.
	 */
	protected function is_checkout() {
		return rcp_is_registration_page();
	}

	/**
	 * Register the order completed hook.
	 */
	protected function register_order_completed_action() {
		add_action( 'rcp_form_processing', array( $this, 'rcp_set_cart_completed' ), 10, 3 );
	}

	/**
	 * Register hooks to keep the cart contents up to date.
	 */
	protected function register_cart_update_hooks() {
		// NO-OP on RCP since there is no "cart".

		// However we do need to filter things so we avoid cart clickthrough
		// double logging.
		add_filter( 'crfw_log_cart_clickthrough', array( $this, 'limit_cart_clickthrough_logging' ), 10, 2 );
	}

	/**
	 * @param $log
	 * @param $cart_id
	 *
	 * @return bool
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function limit_cart_clickthrough_logging( $log, $cart_id ) {
		// The RCP implementation does a redirect on cart clickthrough, which
		// would otherwise cause the event to be logged twice. Here, we block
		// logging unless it's the initial, non-redirected request.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['crfw_redirected'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Order completed callback.
	 *
	 * Retrieves the email associated with the order (In case we can't access the hash.), and
	 * invokes the parent class to update the cart.
	 *
	 * @param int $payment_id The post ID of the order.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function rcp_set_cart_completed( $postvars, $user_id, $price ) {
		$user = new \WP_User( $user_id );
		$this->set_cart_completed( $user->data->user_email, $price );
	}
}
