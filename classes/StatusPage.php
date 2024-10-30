<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Settings;
use Ademti\Crfw\TemplateLoader;

/**
 * Status page class.
 */
class StatusPage {

	private $settings;

	/**
	 * Constructor.
	 *
	 * Enqueue scripts.
	 *
	 * @param Settings $settings Settings object.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		wp_register_script(
			'd3',
			$this->settings->base_url . '/js/d3.min.js',
			array( 'jquery' ),
			CRFW_VERSION,
			true
		);
		wp_register_script(
			'c3',
			$this->settings->base_url . '/js/c3.min.js',
			array( 'd3' ),
			CRFW_VERSION,
			true
		);
		wp_register_script(
			'crfw-admin',
			$this->settings->base_url . '/js/cart-recovery-for-wordpress-admin.js',
			array( 'c3' ),
			CRFW_VERSION,
			true
		);
		wp_enqueue_script( 'crfw-admin' );
		wp_register_script(
			'crfw-tippy',
			$this->settings->base_url . '/js/tippy.all.min.js',
			array(),
			CRFW_VERSION,
			true
		);
		wp_enqueue_script( 'crfw-tippy' );
		wp_enqueue_style(
			'c3',
			$this->settings->base_url . '/css/c3.min.css',
			array(),
			CRFW_VERSION
		);
	}

	/**
	 * Render the content for the page.
	 */
	public function render() {
		$template_loader = new TemplateLoader();
		$variables       = $this->get_summary_stats();
		if ( $variables['recovered_last90'] === $this->settings->engine->currency_format( 0 ) ) {
			$variables['recovery_blocks'] = '';
		} else {
			$variables['recovery_blocks'] = $template_loader->get_template_with_variables(
				'admin',
				'status-page-recovery-blocks',
				$variables
			);
		}
		$variables['last_run_msg'] = $this->get_last_run_message();
		$variables['cron_debug']   = $this->get_cron_debug();
		$variables                 = apply_filters( 'crfw_status_page_variables', $variables );
		$template_loader->output_template_with_variables( 'admin', 'status-page', $variables );
		$this->status_graph();
	}

	private function get_last_run_message() {
		$last_run_ts = get_option( 'crfw_cron_last_run', false );
		if ( empty( $last_run_ts ) ) {
			return '';
		}

		return sprintf(
			// translators: %s is the date that processing last executed.
			__( 'Cart recovery processing last ran at %s', 'cart-recovery' ),
			date_i18n( 'jS F Y, H:ia', $last_run_ts )
		);
	}

	private function get_cron_debug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['cron_debug'] ) ) {
			return '';
		}
		$cron   = _get_cron_array();
		$debug  = '<dl>';
		$debug .= '<dt>Current cron timestamp</dt>';
		// phpcs:disable WordPress.DateTime
		$debug .= '<dd>' . date( 'c' ) . '</dd>';
		// phpcs:enable
		foreach ( $cron as $ts => $item ) {
			$keys   = array_keys( $item );
			$debug .= '<dt>' . esc_html( $keys[0] ) . '</dt>';
			if ( $ts > time() ) {
				$style = 'color: #090';
			} else {
				$style = 'color: #900';
			}
			// phpcs:disable WordPress.DateTime
			$debug .= '<dd style="' . $style . '">' . date( 'c', $ts ) . '</dd>';
			// phpcs:enable
		}
		$debug .= '</dl>';

		return $debug;
	}

	/**
	 * Generate summary stats for all open carts in the last 28 days.
	 *
	 * @return Array  Array of counts per status.
	 */
	private function get_summary_stats() {
		$timestamp                   = gmmktime( 0, 0, 0 );
		$timestamp                   = $timestamp - ( 86400 * 28 );
		$results                     = $this->get_summary_status_stats( $timestamp );
		$results['recovered_last30'] = $this->get_recovered_totals( $timestamp );
		$tracking_started            = get_option( 'crfw_cart_value_tracking_started', 0 );
		if ( $tracking_started > ( time() - ( 90 * 60 * 60 * 24 ) ) ) {
			$results['recovered_last90']       = $this->get_recovered_totals( $tracking_started );
			$results['recovered_last90_label'] = sprintf(
				// translators: %s is the date when the 90 day period runs from.
				__( 'Recovered since %s', 'crfw' ),
				date_i18n( 'jS M Y', $tracking_started )
			);
		} else {
			$results['recovered_last90']       = $this->get_recovered_totals( time() - ( 90 * 60 * 60 * 24 ) );
			$results['recovered_last90_label'] = __( 'Recovered - last 90 days', 'crfw' );
		}

		return $results;
	}

	/**
	 * Generate a value for all recovered carts since $timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return float           Total amount recovered in store currency.
	 */
	private function get_recovered_totals( $timestamp ) {

		global $wpdb, $table_prefix;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(m.value)
				   FROM %i c
			 INNER JOIN %i m
				     ON m.cart_id = c.id
				    AND m.name = 'order_value'
				  WHERE c.status = 'recovered'
				    AND c.completed > %d",
				$table_prefix . 'crfw_cart',
				$table_prefix . 'crfw_cart_meta',
				$timestamp
			)
		);
		if ( empty( $value ) ) {
			$value = 0;
		}

		return $this->settings->engine->currency_format( $value );
	}

	/**
	 * Get a count of carts in various statuses since $timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return array           Array of status => count
	 */
	private function get_summary_status_stats( $timestamp ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$summary = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status,
						COUNT(id) AS count
				   FROM %i
				  WHERE created > %d
			   GROUP BY status
			    ',
				$wpdb->prefix . 'crfw_cart',
				$timestamp
			),
			OBJECT_K
		);
		$results = array();
		foreach ( array( 'pending', 'recovery', 'recovered', 'unrecovered', 'completed' ) as $status ) {
			if ( isset( $summary[ $status ] ) ) {
				$results[ $status . '_cnt' ] = $summary[ $status ]->count;
			} else {
				$results[ $status . '_cnt' ] = 0;
			}
			$results[ $status . '_cnt_wrapper_open' ]  = '';
			$results[ $status . '_cnt_wrapper_close' ] = '';
		}

		return $results;
	}

	/**
	 * Status page graph.
	 */
	public function status_graph() {
		global $wpdb, $table_prefix;

		$graph_data                      = new \stdClass();
		$graph_data->data                = new \stdClass();
		$graph_data->data->order         = 'asc';
		$graph_data->data->type          = 'bar';
		$graph_data->data->groups        = array(
			array(
				__( 'Pending', 'cart-recovery' ),
				__( 'Recovery', 'cart-recovery' ),
				__( 'Recovered', 'cart-recovery' ),
				__( 'Unrecovered', 'cart-recovery' ),
				__( 'Completed normally', 'cart-recovery' ),
			),
		);
		$graph_data->data->columns       = array(
			array( __( 'Pending', 'cart-recovery' ) ),
			array( __( 'Recovery', 'cart-recovery' ) ),
			array( __( 'Recovered', 'cart-recovery' ) ),
			array( __( 'Unrecovered', 'cart-recovery' ) ),
			array( __( 'Completed normally', 'cart-recovery' ) ),
		);
		$graph_data->color               = new \stdClass();
		$graph_data->color->pattern      = array( '#555', '#d46f15', '#191', '#911', '#f0f0f0' );
		$graph_data->legend              = new \StdClass();
		$graph_data->legend->position    = 'right';
		$graph_data->axis                = new \stdClass();
		$graph_data->axis->x             = new \stdClass();
		$graph_data->axis->x->type       = 'category';
		$graph_data->axis->x->categories = array();

		$gmt_timestamp = gmmktime( 0, 0, 0 );
		// phpcs:disable WordPress.DateTime
		$offset = time() - current_time( 'timestamp' );
		// phpcs:enable
		$timestamp = $gmt_timestamp + $offset - ( 86400 * 28 );
		while ( $timestamp < time() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT status,
							COUNT(id) AS count
					   FROM %i
					  WHERE created > %d
					    AND created < %d
				   GROUP BY status
				    ',
					$table_prefix . 'crfw_cart',
					$timestamp,
					$timestamp + 86399
				),
				OBJECT_K
			);
			// phpcs:disable WordPress.DateTime
			$graph_data->axis->x->categories[] = date( 'd M', $timestamp );
			// phpcs:enable
			foreach ( array( 'pending', 'recovery', 'recovered', 'unrecovered', 'completed' ) as $idx => $status ) {
				if ( isset( $stats[ $status ] ) ) {
					$graph_data->data->columns[ $idx ][] = $stats[ $status ]->count;
				} else {
					$graph_data->data->columns[ $idx ][] = 0;
				}
			}
			$timestamp += 86400;
		}
		wp_localize_script( 'crfw-admin', 'crfw_recovery_graph', (array) $graph_data );
	}
}
