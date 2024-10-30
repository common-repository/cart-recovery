<?php

namespace Ademti\Crfw;

class GdprEraser {

	/**
	 * Run the class.
	 *
	 * Registers our exporter with WordPress.
	 */
	public function run() {
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ), 10 );
	}

	/**
	 * Register our eraser with WordPress.
	 *
	 * @param $erasers array of eraser callbacks.
	 *
	 * @return mixed Modified array of eraser callbacks.
	 */
	public function register_eraser( $erasers ) {
		$erasers['cart-recovery'] = array(
			'eraser_friendly_name' => __( 'Abandoned cart recovery', 'cart-recovery' ),
			'callback'             => array( $this, 'eraser_callback' ),
		);

		return $erasers;
	}

	/**
	 * Handle erasing.
	 *
	 * @param string $email The email to be removed.
	 * @param int    $page The page being processed.
	 *
	 * @return array
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function eraser_callback( $email, $page = 1 ) {
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$per_page = 10;

		$carts = $this->fetch_carts( $email, $per_page );
		foreach ( $carts as $cart ) {
			$cart_object = new Cart( $cart->id );
			$cart_object->anonymise();
			$cart_object->save();
		}

		$done = count( $carts ) < $per_page;

		return array(
			'items_removed'  => count( $carts ),
			'items_retained' => false,
			'messages'       => [],
			'done'           => $done,
		);
	}

	private function fetch_carts( $email, $per_page ) {
		global $wpdb, $table_prefix;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT *
			       FROM %i
		          WHERE email = %s
		          LIMIT %d, %d',
				$table_prefix . 'crfw_cart',
				$email,
				0,
				$per_page
			)
		);
	}
}
