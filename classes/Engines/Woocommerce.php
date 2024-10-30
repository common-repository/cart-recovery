<?php

namespace Ademti\Crfw\Engines;

use Ademti\Crfw\Engines\AbstractEngine;
use Ademti\Crfw\Cart;
use Ademti\Crfw\Settings;
use WC_Customer;
use WC_Order;

/**
 * WooCommerce Frontend Engine.
 */
class Woocommerce extends AbstractEngine {

	private $cart_to_repopulate = null;

	/**
	 * Constructor.
	 *
	 * Run parent constructor, then WooCommerce specific actions.
	 *
	 * @param Settings $settings
	 */
	public function __construct( Settings $settings ) {
		parent::__construct( $settings );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'woocommerce_navigation_pages_with_tabs', array( $this, 'woocommerce_navigation_pages_with_tabs' ) );
	}

	/**
	 * Register the fact that our screen has tabs for the WC navigation bar.
	 *
	 * @param $pages
	 *
	 * @return mixed
	 */
	public function woocommerce_navigation_pages_with_tabs( $pages ) {
		$pages['cart_recovery_for_wordpress'] = 'cart-recovery';

		return $pages;
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function admin_menu() {
		if ( ! function_exists( 'wc_admin_connect_page' ) ) {
			return;
		}
		wc_admin_connect_page(
			array(
				'id'        => 'cart-recovery',
				'screen_id' => 'toplevel_page_cart_recovery_for_wordpress-cart-recovery',
				'title'     => __( 'Cart Recovery for WordPress', 'cart-recovery' ),
				'path'      => 'admin.php?page=cart_recovery_for_wordpress',
			)
		);
		wc_admin_connect_page(
			array(
				'id'        => 'cart-recovery-status',
				'screen_id' => 'toplevel_page_cart_recovery_for_wordpress-status',
				'title'     => __( 'Cart Recovery for WordPress', 'cart-recovery' ),
				'path'      => 'admin.php?page=cart_recovery_for_wordpress&tab=status',
			)
		);
		wc_admin_connect_page(
			array(
				'id'        => 'cart-recovery-main',
				'screen_id' => 'toplevel_page_cart_recovery_for_wordpress-main',
				'title'     => __( 'Tracking options', 'cart-recovery' ),
				'parent'    => 'cart-recovery-status',
			)
		);
		wc_admin_connect_page(
			array(
				'id'        => 'cart-recovery-email',
				'screen_id' => 'toplevel_page_cart_recovery_for_wordpress-email',
				'title'     => __( 'Campaign settings', 'cart-recovery' ),
				'parent'    => 'cart-recovery-status',
			)
		);
		wc_admin_connect_page(
			array(
				'id'        => 'cart-recovery-info',
				'screen_id' => 'toplevel_page_cart_recovery_for_wordpress-info',
				'title'     => __( 'Cart list', 'cart-recovery' ),
				'parent'    => 'cart-recovery-status',
			)
		);
	}

	/**
	 * Get the cart details in a standard format.
	 */
	protected function get_cart_details() {
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();
		if ( ! count( $items ) ) {
			return;
		}
		$data             = array(
			'currency'        => get_woocommerce_currency(),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'contents'        => array(),
		);
		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
		foreach ( $items as $item_key => $item ) {
			$product = $item['data'];
			if ( is_callable( array( $product, 'get_id' ) ) ) {
				// WooCommerce 2.7+
				$product_id = $product->get_id();
			} else {
				$product_id = $product->id;
			}
			$image_id  = $product->get_image_id();
			$image_url = '';
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
			if ( 'incl' === $tax_display_mode ) {
				$price = round( $item['line_total'] + $item['line_tax'], 2 );
			} else {
				$price = round( $item['line_total'], 2 );
			}
			$js_item            = apply_filters(
				'crfw_cart_item',
				array(
					'name'           => get_the_title( $product_id ),
					'price'          => $price,
					'image'          => $image_url,
					'quantity'       => $item['quantity'],
					'product_id'     => $item['product_id'],
					'variation_id'   => $item['variation_id'],
					'variation_data' => $item['variation'],
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

		// Try and grab the information from the customer object.
		$customer = WC()->customer;
		if ( ! $customer ) {
			return $details;
		}
		if ( version_compare( WC()->version, '2.7.0', '>=' ) ) {
			$details['email'] = $customer->get_billing_email();
			// Use customer name if we have one.
			$details['first_name'] = $customer->get_first_name();
			$details['surname']    = $customer->get_last_name();
			// Otherwise try billing name...
			if ( empty( $details['first_name'] ) && empty( $details['surname'] ) ) {
				$details['first_name'] = $customer->get_billing_first_name();
				$details['surname']    = $customer->get_billing_last_name();
			}
			// Otherwise, try shipping name...
			if ( empty( $details['first_name'] ) && empty( $details['surname'] ) ) {
				$details['first_name'] = $customer->get_shipping_first_name();
				$details['surname']    = $customer->get_shipping_last_name();
			}
		} else {
			$details['email']      = $customer->email;
			$details['first_name'] = $customer->first_name;
			$details['surname']    = $customer->last_name;
		}

		return $details;
	}

	/**
	 * Repopulate the cart.
	 */
	public function repopulate_cart( Cart $cart ) {
		// Store the cart since WooCommerce won't let us do this until wp_loaded.
		$this->cart_to_repopulate = $cart;
		// Hook to say we need to repopulate.
		add_action( 'wp_loaded', array( $this, 'actually_repopulate_cart' ) );
	}

	/**
	 * Actually handle cart re-population.
	 */
	public function actually_repopulate_cart() {

		global $woocommerce;

		// Clear any old carts.
		wc_empty_cart();

		$cart_contents = $this->cart_to_repopulate->cart_details['contents'];
		foreach ( $cart_contents as $line_item ) {
			$product_id     = $line_item['product_id'];
			$variation_id   = $line_item['variation_id'];
			$quantity       = $line_item['quantity'];
			$variation_data = $line_item['variation_data'];
			$cart_item_data = isset( $line_item['cart_item_data'] ) ?
				$line_item['cart_item_data'] :
				array();
			$woocommerce->cart->add_to_cart(
				$product_id,
				$quantity,
				$variation_id,
				$variation_data,
				$cart_item_data
			);
		}

		// Set the email into the customer record if none exists.
		$current_email = WC()->customer->get_billing_email();
		if ( empty( $current_email ) && ! empty( $this->cart_to_repopulate->email ) ) {
			WC()->customer->set_billing_email( $this->cart_to_repopulate->email );
		}

		do_action( 'crfw_after_repopulate_cart', $this->cart_to_repopulate );
		wp_safe_redirect(
			apply_filters( 'crfw_return_redirect_url', $this->get_checkout_url(), $this->cart_to_repopulate ),
			'303'
		);
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
			// @TODO Can we add variation info in a generic way?
			// FIXME - needs doing if we're keying by name
		}

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	public function format_price( $price, $currency ) {
		return get_woocommerce_currency_symbol( $currency ) . sprintf( '%2.2f', $price );
	}

	/**
	 * Generate a string describing the value of a cart.
	 */
	public function get_cart_value( $cart_details ) {

		if ( empty( $cart_details['contents'] ) ) {
			return _x( '-', 'Represents a zero-value cart', 'cart-recovery' );
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
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			WC()->frontend_includes();
		}

		return wc_get_checkout_url();
	}

	/**
	 * Return the suffix for the engine.
	 *
	 * @return string The suffix.
	 */
	protected function get_suffix() {
		return 'woocommerce';
	}

	/**
	 * Determine if the current page is a checkout page.
	 *
	 * @return boolean True if the current page is the checkout page.
	 */
	protected function is_checkout() {
		return is_checkout();
	}

	/*
	 * Generate a sample cart details array.
	 */
	public function get_sample_cart_details() {
		$data = array(
			'currency'        => get_woocommerce_currency(),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'contents'        => array(),
		);
		// Pick some sample products to use.
		$products = $this->get_sample_products();

		// Generate the details array in the correct format.
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation->get_price() !== '' ) {
						$product = $variation;
						break;
					}
				}
			}
			if ( is_callable( array( $product, 'get_id' ) ) ) {
				$product_id = $product->get_id();
			} else {
				$product_id = $product->id;
			}
			$image_id  = $product->get_image_id();
			$image_url = wp_get_attachment_image_src(
				$image_id,
				apply_filters(
					'crfw_image_size',
					'thumbnail'
				)
			);
			$image_url = $image_url[0];
			$qty       = wp_rand( 1, 3 );
			$price     = $product->get_price();
			if ( $price === '' ) {
				$price = '0';
			}
			$item               = array(
				'name'       => $product->get_title(),
				'price'      => $price * $qty,
				'image'      => $image_url,
				'quantity'   => $qty,
				'product_id' => $product_id,
			);
			$data['contents'][] = $item;
		}

		return $data;
	}

	/**
	 * Format a numeric amount based on the store currency.
	 */
	public function currency_format( $amount ) {
		return wp_strip_all_tags( wc_price( $amount ) );
	}

	/**
	 * Find some downloads we can use in the sample content.
	 *
	 * @TODO: We do not use wc_get_products() on WC 2.7+ yet as it doesn't
	 * provide the same querying capabilities that we use here.
	 *
	 * https://github.com/woocommerce/woocommerce/issues/12961
	 */
	public function get_sample_products() {
		// Find the most popular two products that have a thumbnail.
		add_filter(
			'woocommerce_product_data_store_cpt_get_products_query',
			[ $this, 'filter_sample_products_wc_product_query' ],
			10,
			2
		);
		$args     = [
			'status'     => 'publish',
			'visibility' => 'visible',
			'limit'      => 2,
		];
		$products = wc_get_products( $args );
		remove_filter(
			'woocommerce_product_data_store_cpt_get_products_query',
			[ $this, 'filter_sample_products_wc_product_query' ],
			10,
			2
		);
		if ( count( $products ) ) {
			return $products;
		}
		// If none found, just return two products.
		// Same query args, but no customisation via the filter.
		return wc_get_products( $args );
	}

	/**
	 * @param $query
	 * @param $query_vars
	 *
	 * @return mixed
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function filter_sample_products_wc_product_query( $query, $query_vars ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query['meta_key'] = 'total_sales';
		$query['orderby']  = 'meta_value_num';
		$query['order']    = 'DESC';
		if ( empty( $query['meta_query'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$query['meta_query'] = [];
		}
		$query['meta_query'][] = [
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		];
		$query['meta_query'][] = [
			'key'     => '_price',
			'value'   => 0,
			'type'    => 'NUMERIC',
			'compare' => '>',
		];

		return $query;
	}

	/**
	 * Register the order completed hook.
	 */
	protected function register_order_completed_action() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'woo_set_order_id_completed' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'woo_set_order_completed' ) );
	}

	/**
	 * Register hooks to keep the cart contents up to date.
	 */
	protected function register_cart_update_hooks() {
		// Updates when cart details change.
		add_action( 'woocommerce_cart_updated', array( $this, 'update_cart' ) );
		// Updates when the customer information changes.
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'update_customer' ), 10, 0 );
	}

	/**
	 * Update the cart record with the updated customer details.
	 *
	 * @return void
	 */
	public function update_customer() {
		$cart_details = $this->settings->engine->get_cart_details();
		$user_details = $this->settings->engine->get_user_details();
		$this->record_cart(
			$user_details['email'],
			$user_details['first_name'],
			$user_details['surname'],
			$cart_details,
			true
		);
	}

	/**
	 * Order completed callback for non-block-based checkout.
	 *
	 * Retrieves the email associated with the order (In case we can't access the hash.), and
	 * invokes the parent class to update the cart.
	 *
	 * @param int $order_id The post ID of the order.
	 */
	public function woo_set_order_id_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		$this->woo_set_order_completed( $order );
	}

	/**
	 * Order completed callback.
	 *
	 * Retrieves the email associated with the order (In case we can't access the hash), and
	 * invokes the parent class to update the cart.
	 *
	 * @param WC_Order $order The order.
	 */
	public function woo_set_order_completed( $order ) {
		$email       = $order->get_billing_email();
		$order_value = (float) $order->get_total();
		$this->set_cart_completed( $email, $order_value );
	}
}
