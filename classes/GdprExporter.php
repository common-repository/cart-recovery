<?php

namespace Ademti\Crfw;

class GdprExporter {

	/**
	 * Run the class.
	 *
	 * Registers our exporter with WordPress.
	 */
	public function run() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ), 10 );
	}

	/**
	 * Register our exporter with WordPress.
	 *
	 * @param $exporters array of exporter callbacks.
	 *
	 * @return mixed Modified array of exporter callbacks.
	 */
	public function register_exporter( $exporters ) {
		$exporters['cart-recovery'] = array(
			'exporter_friendly_name' => __( 'Cart Recovery for WordPress', 'cart-recovery' ),
			'callback'               => array( $this, 'export_callback' ),
		);

		return $exporters;
	}

	public function export_callback( $email, $page = 1 ) {

		$page     = (int) $page;
		$data     = array();
		$per_page = 10;

		$carts = $this->fetch_carts( $email, $page, $per_page );
		foreach ( $carts as $cart ) {
			$data[] = $this->assemble_cart_data( $cart );
		}

		$done = count( $carts ) < $per_page;

		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	private function fetch_carts( $email, $page, $per_page ) {
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
				( $page - 1 ) * $per_page,
				$per_page
			)
		);
	}


	private function assemble_cart_data( $cart ) {
		$data = [
			// phpcs:disable WordPress.DateTime
			[
				'name'  => __( 'Cart first captured', 'cart-recovery' ),
				'value' => date( 'Y-m-d H:i:s', $cart->created ),
			],
			[
				'name'  => __( 'Cart last updated', 'cart-recovery' ),
				'value' => date( 'Y-m-d H:i:s', $cart->updated ),
			],
			// phpcs:enable
			[
				'name'  => __( 'First Name', 'cart-recovery' ),
				'value' => $cart->first_name,
			],
			[
				'name'  => __( 'Surname', 'cart-recovery' ),
				'value' => $cart->surname,
			],
			[
				'name'  => __( 'Status', 'cart-recovery' ),
				'value' => ucfirst( $cart->status ),
			],
		];
		$cart_details = unserialize( $cart->cart_details );

		foreach ( $cart_details['contents'] as $item ) {
			$item_name = $item['name'];
			if ( ! empty( $item['variation_data'] ) ) {
				$item_name .= ' (';
				$attrs      = [];
				foreach ( $item['variation_data'] as $attr => $value ) {
					$attr    = str_replace( 'attribute_pa_', '', $attr );
					$attrs[] = $attr . ': ' . $value;
				}
				$item_name .= implode( ', ', $attrs );
				$item_name .= ')';
			}
			$item_description = sprintf(
				// translators: %1$d is a quantity, %2$s is the item name.
				__( '%1$d x %2$s', 'cart-recovery' ),
				$item['quantity'],
				$item_name
			);
			$data[] = [
				'name'  => __( 'Cart item', 'cart-recovery' ),
				'value' => $item_description,
			];
		}

		return [
			'group_id'    => 'cart-recovery',
			'group_label' => __( 'Abandoned cart recovery', 'cart-recovery' ),
			'item_id'     => 'cart-recover-' . $cart->id,
			'data'        => $data,
		];
	}
}
