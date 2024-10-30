<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Settings;
use Ademti\Crfw\Cart;

class CronHandler {

	/**
	 * How long (in seconds) a cart can stay in "pending" before being considered as abandoned.
	 *
	 * Defaults to 1800 seconds. Filterable via crfw_pending_duration.
	 *
	 * @see __construct().
	 *
	 * @var int
	 */
	private $pending_duration;

	/**
	 * How long (in seconds) after the final contact a cart is considered unrecovered.
	 *
	 * Defaults to 172800. Filterable via crfw_unrecovered_duration.
	 *
	 * @var int
	 */
	private $unrecovered_duration;

	/**
	 * Settings instance.
	 *
	 * @var \Ademti\Crfw\\Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct( Settings $settings ) {
		$this->settings             = $settings;
		$this->pending_duration     = apply_filters( 'crfw_pending_duration', 1800 );
		$this->unrecovered_duration = apply_filters( 'crfw_unrecovered_duration', 172800 );
	}

	/**
	 * Cron callback.
	 *
	 * Runs all the automation.
	 */
	public function cron() {
		$this->put_pending_carts_into_recovery();
		$this->run_campaigns();
		$this->record_last_run_time();
		$this->anonymise_old_carts();
		$this->cleanup_anonymised_cart_meta();
	}

	/**
	 * Update all pending carts older than $pending_duration to be in recovery.
	 */
	private function put_pending_carts_into_recovery() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
		           FROM {$wpdb->prefix}crfw_cart
			      WHERE status = 'pending'
			        AND updated < ( unix_timestamp() - %d )",
				$this->pending_duration
			)
		);
		foreach ( $cart_ids as $cart_id ) {
			$cart = new Cart( $cart_id );
			$cart->put_in_recovery( $this->pending_duration );
			$cart->save();
		}
	}

	/**
	 * Run any registered campaigns.
	 */
	private function run_campaigns() {
		do_action( 'crfw_run_campaigns' );
	}

	/**
	 * Record the time that we ran.
	 */
	private function record_last_run_time() {
		// phpcs:disable WordPress.DateTime
		update_option( 'crfw_cron_last_run', current_time( 'timestamp' ) );
		// phpcs: enable
	}

	private function anonymise_old_carts() {

		global $wpdb;

		$period = (int) $this->settings->crfw_anonymise_period;
		if ( $period < 1 ) {
			// Bail if carts are set never to anonymise.
			return;
		}

		$chunk_size = apply_filters( 'crfw_anonymisation_chunk_size', 20 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id
		           FROM %i c
		      LEFT JOIN %i cm
		             ON c.id = cm.cart_id
		            AND cm.name = 'cart_anonymised'
			      WHERE updated < ( unix_timestamp() - %d )
			        AND c.status IN ( 'completed', 'unrecovered', 'recovered', 'empty' )
			        AND cm.id IS NULL
			      LIMIT %d",
				$wpdb->prefix . 'crfw_cart',
				$wpdb->prefix . 'crfw_cart_meta',
				$period,
				$chunk_size
			)
		);
		foreach ( $cart_ids as $cart_id ) {
			$cart = new Cart( $cart_id );
			$cart->anonymise();
			$cart->save();
		}
	}

	/**
	 * Looks for cart meta that has been marked as anonymised but still contains user ID references and cleans them up.
	 *
	 * Delayed processing for database upgrade 5 - see Main::upgrade_db_to_5()
	 *
	 * @return void
	 */
	private function cleanup_anonymised_cart_meta() {
		global $wpdb;

		// Check whether we have anything to do.
		// The upgrade routine sets this WP variable to 0 to trigger this process.
		$last_cleaned_cart_id = get_option( 'crfw_anon_meta_clearup_processed_until', null );
		if ( $last_cleaned_cart_id === null ) {
			return;
		}

		// Pull a list of the next 25 (filterable) anonymised cart IDs.
		$chunk_size = apply_filters( 'crfw_cleanup_anonymisation_chunk_size', 25 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT cart_id
               FROM %i
              WHERE cart_id > %d
                AND name = %s
           ORDER BY cart_id ASC
              LIMIT %d',
				$wpdb->prefix . 'crfw_cart_meta',
				$last_cleaned_cart_id,
				'cart_anonymised',
				$chunk_size
			)
		);

		// If there are no results, then we have processed all anonymised cart IDs.
		// Clear the option so we no longer have to check again, since any carts anonymised from this point will
		// be treated correctly.
		if ( empty( $cart_ids ) ) {
			delete_option( 'crfw_anon_meta_clearup_processed_until' );
		}

		// If we get some carts, iterate through them, and ensure any user_id meta is removed
		// updating our option to track progress as we do.
		foreach ( $cart_ids as $cart_id ) {
			$cart = new Cart( $cart_id );
			$cart->delete_meta( 'user_id' );
			$cart->save();
			update_option( 'crfw_anon_meta_clearup_processed_until', $cart_id );
		}
	}
}
