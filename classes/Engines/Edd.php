<?php

namespace Ademti\Crfw\Engines;

use Ademti\Crfw\Engines\AbstractEngine;
use Ademti\Crfw\Cart;
use EDD_Customer;
use EDD_Payment;

/**
 * EDD Frontend Engine.
 */
class Edd extends AbstractEngine {

	/**
	 * Get the cart details in a standard format.
	 */
	public function get_cart_details() {
		$cart = edd_get_cart_contents();
		if ( ! $cart ) {
			return;
		}

		$data = array(
			'currency'        => edd_get_currency(),
			'currency_symbol' => edd_currency_symbol(),
			'contents'        => array(),
		);
		foreach ( $cart as $item_key => $item ) {
			$download = new \EDD_Download( $item['id'] );

			// Get the price of the download, or the specific variation.
			$download_price = $download->get_price();
			if ( isset( $item['options']['price_id'] ) ) {
				$download_prices = $download->get_prices();
				if ( isset( $download_prices[ $item['options']['price_id'] ] ) ) {
					$download_price = $download_prices[ $item['options']['price_id'] ]['amount'];
				}
			}

			// Get the item name.
			if ( is_callable( 'edd_get_cart_item_name' ) ) {
				$cart_item_name = edd_get_cart_item_name( $item );
			} else {
				$cart_item_name = get_the_title( $item['id'] );
			}

			// Get the item image.
			$image_url = '';
			$image_id  = get_post_thumbnail_id( $item['id'] );
			if ( $image_id ) {
				$image_src = wp_get_attachment_image_src(
					$image_id,
					apply_filters(
						'crfw_image_size',
						'thumbnail'
					)
				);
				if ( ! empty( $image_src ) ) {
					$image_url = $image_src[0];
				}
			}

			$js_item            = apply_filters(
				'crfw_cart_item',
				array(
					'name'     => $cart_item_name,
					'price'    => $download_price * $item['quantity'],
					'image'    => $image_url,
					'quantity' => $item['quantity'],
					'internal' => $item,
				),
				$item,
				$item_key
			);
			$data['contents'][] = $js_item;
		}

		return $data;
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
		// Try and find the EDD_Customer record.
		$customer = new EDD_Customer( $user->ID, true );
		if ( ! $customer ) {
			// Use the details from the user record.
			$details['email']      = $user->user_email;
			$names                 = explode( ' ', $user->nicename );
			$details['surname']    = array_pop( $names );
			$details['first_name'] = implode( ' ', $names );
		} else {
			// Use the details from the customer record.
			$details['email']      = $customer->email;
			$names                 = explode( ' ', $customer->name );
			$details['surname']    = array_pop( $names );
			$details['first_name'] = implode( ' ', $names );
		}

		return $details;
	}

	/**
	 * Repopulate the cart.
	 */
	public function repopulate_cart( Cart $cart ) {
		// Clear any old carts.
		edd_empty_cart();

		$cart_contents = $cart->cart_details['contents'];
		foreach ( $cart_contents as $line_item ) {
			$download_id = $line_item['internal']['id'];
			$option_id   = isset( $line_item['internal']['options']['price_id'] ) ? $line_item['internal']['options']['price_id'] : null;
			$quantity    = $line_item['internal']['quantity'];
			if ( $option_id ) {
				$options = array(
					'price_id' => $option_id,
					'quantity' => $quantity,
				);
			} else {
				$options = array(
					'quantity' => $quantity,
				);
			}
			edd_add_to_cart( $download_id, $options );
		}
		do_action( 'crfw_after_repopulate_cart', $cart );
		wp_safe_redirect(
			apply_filters( 'crfw_return_redirect_url', $this->get_checkout_url(), $cart ),
			'303'
		);
		exit();
	}

	/**
	 * Get the items in the cart.
	 *
	 * @param  array  $cart_details  The cart details stored.
	 *
	 * @return array                Array of products and their counts.
	 */
	public function get_cart_items( $cart_details ) {
		if ( empty( $cart_details['contents'] ) ) {
			return array();
		}
		$results = array();
		foreach ( $cart_details['contents'] as $item ) {
			$results[ $item['name'] ] = $item['internal']['quantity'];
		}

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	public function format_price( $price, $currency ) {
		return edd_currency_symbol( $currency ) . sprintf( '%2.2f', $price );
	}

	/**
	 * Generate a string describing the value of a cart.
	 */
	public function get_cart_value( $cart_details ) {
		if ( empty( $cart_details['contents'] ) ) {
			return $this->format_price( 0, $cart_details['currency'] );
		}
		$total = 0;
		foreach ( $cart_details['contents'] as $item ) {
			$total += $item['price'];
		}

		return $this->format_price( $total, $cart_details['currency'] );
	}

	/**
	 * Get the checkout URL.
	 * @return string  The checkout URL.
	 */
	public function get_checkout_url() {
		return edd_get_checkout_uri();
	}

	/*
	 * Generate a sample cart details array.
	 */
	public function get_sample_cart_details() {
		$data = array(
			'currency'        => edd_get_currency(),
			'currency_symbol' => edd_currency_symbol(),
			'contents'        => array(),
		);
		// Pick some sample products to use.
		$downloads = $this->get_sample_downloads();

		// Generate the details array in the correct format.
		foreach ( $downloads as $download ) {
			$download  = new \EDD_Download( $download->ID );
			$image_id  = get_post_thumbnail_id( $download->ID );
			$image_url = wp_get_attachment_image_src(
				$image_id,
				apply_filters(
					'crfw_image_size',
					'thumbnail'
				)
			);
			$image_url = $image_url[0];
			$qty       = wp_rand( 1, 3 );
			$options   = array();

			$download_price = $download->get_price();
			if ( edd_has_variable_prices( $download->ID ) ) {
				$prices              = $download->get_prices();
				$options['price_id'] = array_rand( $prices );
				$download_price      = $prices[ $options['price_id'] ]['amount'];
			}

			$cart_item = [
				'id'       => $download->ID,
				'quantity' => $qty,
				'options'  => $options,
			];

			// Get the item name.
			if ( is_callable( 'edd_get_cart_item_name' ) ) {
				$cart_item_name = edd_get_cart_item_name( $cart_item );
			} else {
				$cart_item_name = get_the_title( $download->ID );
			}

			$item               = array(
				'name'     => $cart_item_name,
				'price'    => $download_price * $qty,
				'image'    => $image_url,
				'quantity' => $qty,
				'internal' => $cart_item,
			);
			$data['contents'][] = $item;
		}

		return $data;
	}

	/**
	 * Format a numeric amount based on the store currency.
	 */
	public function currency_format( $amount ) {
		return edd_currency_filter( edd_format_amount( $amount ) );
	}

	/**
	 * Find some downloads we can use in the sample content.
	 */
	private function get_sample_downloads() {
		$args      = array(
			'post_type'   => 'download',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'    => '_edd_download_sales',
			'orderby'     => 'meta_value_num',
			'order'       => 'DESC',
			'numberposts' => 2,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'  => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		);
		$downloads = get_posts( $args );
		if ( count( $downloads ) ) {
			return $downloads;
		}
		unset( $args['meta_query'] );

		return get_posts( $args );
	}

	/**
	 * Return the suffix for the engine.
	 *
	 * @return string The suffix.
	 */
	protected function get_suffix() {
		return 'edd';
	}

	/**
	 * Determine if the current page is a checkout page.
	 *
	 * @return boolean True if the current page is the checkout page.
	 */
	protected function is_checkout() {
		return edd_is_checkout();
	}

	/**
	 * Register the order completed hook.
	 */
	protected function register_order_completed_action() {
		add_action( 'edd_complete_purchase', array( $this, 'edd_set_cart_completed' ) );
	}

	/**
	 * Register hooks to keep the cart contents up to date.
	 *
	 * Also register a hook to stop double logging of click-throughs due to the
	 * checkout redirect.
	 */
	protected function register_cart_update_hooks() {
		add_action( 'edd_post_add_to_cart', array( $this, 'update_cart' ) );
		add_action( 'edd_post_remove_from_cart', array( $this, 'update_cart' ) );
		add_action( 'edd_after_set_cart_item_quantity', array( $this, 'update_cart' ) );
	}

	/**
	 * Order completed callback.
	 *
	 * Retrieves the email associated with the order (In case we can't access the hash.), and
	 * invokes the parent class to update the cart.
	 *
	 * @param  int  $payment_id  The post ID of the order.
	 */
	public function edd_set_cart_completed( $payment_id ) {
		$email   = get_post_meta( $payment_id, '_edd_payment_user_email', true );
		$payment = new EDD_Payment( $payment_id );
		$this->set_cart_completed( $email, $payment->total );
	}
}
