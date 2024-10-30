<?php

namespace Ademti\Crfw\Engines;

use WPSC_Purchase_Log;
use Ademti\Crfw\Engines\AbstractEngine;
use Ademti\Crfw\Cart;

/**
 * WP e-Commerce Frontend Engine.
 */
class Wpecommerce extends AbstractEngine {

	/**
	 * Get the cart details in a standard format.
	 */
	public function get_cart_details() {

		global $wpsc_cart;

		if ( ! count( $wpsc_cart->cart_items ) ) {
			return;
		}
		$data = array(
			'currency'        => wpsc_get_currency_code(),
			'currency_symbol' => wpsc_get_currency_symbol(),
			'contents'        => array(),
		);
		foreach ( $wpsc_cart->cart_items as $item_key => $item ) {
			$image_url = '';
			$image_id  = get_post_thumbnail_id( $item->product_id );
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
					'price'            => $item->total_price,
					'name'             => $item->product_name,
					'image'            => $image_url,
					'quantity'         => $item->quantity,
					'product_id'       => $item->product_id,
					'variation_data'   => $item->variation_data,
					'variation_values' => $item->variation_values,
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
		if ( function_exists( 'wpsc_get_customer_meta' ) ) {
			$details['email']      = wpsc_get_customer_meta( 'billingemail' );
			$details['first_name'] = wpsc_get_customer_meta( 'billingfirstname' );
			$details['surname']    = wpsc_get_customer_meta( 'billinglastname' );
		}

		return $details;
	}


	/**
	 * Repopulate the cart.
	 */
	public function repopulate_cart( Cart $cart ) {
		global $wpsc_cart;

		// Clear any old carts.
		$wpsc_cart->empty_cart();

		// Add the items back into the basket.
		$cart_contents = $cart->cart_details['contents'];
		foreach ( $cart_contents as $line_item ) {
			if ( ! empty( $line_item['variation_values'] ) ) {
				$variation_values = $line_item['variation_values'];
			} else {
				$variation_values = '';
			}
			$parameters = array(
				'variation_values' => $variation_values,
				'quantity'         => $line_item['quantity'],
				'provided_price'   => '',
				'comment'          => '',
				'time_requested'   => '',
				'custom_message'   => '',
				'file_data'        => '',
				'is_customisable'  => '',
				'meta'             => '',
			);
			$wpsc_cart->set_item( $line_item['product_id'], $parameters );
		}
		do_action( 'crfw_after_repopulate_cart', $cart );
		// Redirects go to different places depending on the theme
		// engine in use.
		if ( ! is_callable( 'wpsc_is_theme_engine' ) ||
			wpsc_is_theme_engine( '1.0' ) ||
			! is_callable( 'wpsc_get_cart_url' ) ) {
			// Redirect to the checkout page.
			wp_safe_redirect(
				apply_filters( 'crfw_return_redirect_url', $this->get_checkout_url(), $cart ),
				'303'
			);
		} else {
			// Redirect to the cart page.
			wp_safe_redirect(
				apply_filters( 'crfw_return_redirect_url', wpsc_get_cart_url(), $cart ),
				'303'
			);
		}
		exit();
	}

	/**
	 * Get the items in the cart.
	 *
	 * @param array $cart_details The cart details stored.
	 *
	 * @return array                Array of products and their counts.
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
		return wpsc_get_currency_symbol( $currency ) . sprintf( '%2.2f', $price );
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
		if ( function_exists( 'wpsc_get_checkout_url' ) ) {
			return wpsc_get_checkout_url();
		} else {
			return get_option( 'checkout_url' );
		}
	}

	/*
	 * Generate a sample cart details array.
	 */
	public function get_sample_cart_details() {
		$data = array(
			'currency'        => wpsc_get_currency_code(),
			'currency_symbol' => wpsc_get_currency_symbol(),
			'contents'        => array(),
		);
		// Pick some sample products to use.
		$products = $this->get_sample_products();
		// Generate the details arrauy in the correct format.
		foreach ( $products as $product ) {
			$product            = new \WPSC_Product( $product->ID );
			$image_id           = get_post_thumbnail_id( $product->post->ID );
			$image_url          = wp_get_attachment_image_src(
				$image_id,
				apply_filters(
					'crfw_image_size',
					'thumbnail'
				)
			);
			$image_url          = $image_url[0];
			$qty                = wp_rand( 1, 3 );
			$item               = array(
				'name'       => get_the_title( $product->post->ID ),
				'price'      => $product->price * $qty,
				'image'      => $image_url,
				'quantity'   => $qty,
				'product_id' => $product->post->ID,
			);
			$data['contents'][] = $item;
		}

		return $data;
	}

	/**
	 * Format a numeric amount based on the store currency.
	 */
	public function currency_format( $amount ) {
		return wpsc_currency_display( $amount );
	}

	/**
	 * Find some downloads we can use in the sample content.
	 */
	private function get_sample_products() {
		$args     = array(
			'post_type'   => 'wpsc-product',
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
		$products = get_posts( $args );
		if ( count( $products ) ) {
			return $products;
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
		return 'wpecommerce';
	}

	/**
	 * Determine if the current page is a checkout page.
	 *
	 * @return boolean True if the current page is the checkout page.
	 */
	protected function is_checkout() {
		return wpsc_is_checkout();
	}

	/**
	 * Register the order completed hook.
	 */
	protected function register_order_completed_action() {
		add_action( 'wpsc_purchase_log_save', array( $this, 'wpecommerce_set_cart_completed' ) );
	}

	/**
	 * Register hooks to keep the cart contents up to date.
	 */
	protected function register_cart_update_hooks() {
		add_action( 'wpsc_edit_item', array( $this, 'update_cart' ) );
		add_action( 'wpsc_add_item', array( $this, 'update_cart' ) );
		add_action( 'wpsc_remove_item', array( $this, 'update_cart' ) );
	}

	/**
	 * Order completed callback.
	 *
	 * Retrieves the email associated with the order (In case we can't access the hash.), and
	 * invokes the parent class to update the cart.
	 *
	 * @param WPSC_Purchase_Log $purchase_log The order.
	 */
	public function wpecommerce_set_cart_completed( WPSC_Purchase_Log $purchase_log ) {
		$email = wpsc_get_buyers_email( $purchase_log->get( 'id' ) );
		if ( is_callable( array( $purchase_log, 'get_total' ) ) ) {
			$order_value = $purchase_log->get_total();
		} else {
			$order_value = $purchase_log->get( 'totalprice' );
		}
		$this->set_cart_completed( $email, $order_value );
	}
}
