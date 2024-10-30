<?php

/*
 * Plugin Name: Cart recovery for WordPress
 * Plugin URI: https://wp-cart-recovery.com
 * Description: An easy-to-use plugin that allows you to capture abandoned, and failed orders, and follow up.
 * Author: Ademti Software
 * Version: 3.3.3
 * WC requires at least: 9.0
 * WC tested up to: 9.4
 * Author URI: https://wp-cart-recovery.com/
 * Text Domain: cart-recovery
 * License: GPLv2
 * Domain Path: /languages
*/

/**
 * Copyright (c) 2017-2024 Ademti Software Ltd. // www.ademti-software.co.uk
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'CRFW_VERSION', '3.3.3' );
define( 'CRFW_DB_VERSION', 5 );

if ( version_compare( phpversion(), '5.5', '<' ) ) {

	add_action( 'admin_init', 'crfw_plugin_deactivate' );
	add_action( 'admin_notices', 'crfw_plugin_admin_notice' );

	function crfw_plugin_deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	function crfw_plugin_admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="error"><p><strong>Cart Recovery for WordPress</strong> requires PHP version 5.5 or above.</p></div>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
} else {

	// Add autoloader.
	require_once( dirname( __FILE__ ) . '/autoload.php' );

	/**
	 * Install function. Create the table to store the replacements
	 */
	function crfw_install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create the tables we need.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . 'crfw_cart';
		$sql = "CREATE TABLE $table_name (
		            id int(11) NOT NULL AUTO_INCREMENT,
		            email varchar(1024) NOT NULL,
		            first_name varchar(1024),
		            surname varchar(1024),
		            status varchar(16) NOT NULL DEFAULT 'pending',
		            cart_details text,
		            created int(11) NOT NULL,
		            updated int(11) NOT NULL,
		            completed int(11),
		            PRIMARY KEY  (id),
		            KEY status_idx (status),
		            KEY completed_idx (completed)
		        ) $charset_collate";
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'crfw_cart_event';
		$sql = "CREATE TABLE $table_name (
		            id INT(11) NOT NULL AUTO_INCREMENT,
		            cart_id INT(11) NOT NULL,
		            type VARCHAR(255) NOT NULL DEFAULT 'note',
		            details TEXT,
		            created INT(11) NOT NULL,
		            PRIMARY KEY  (id)
		        ) $charset_collate";
		dbDelta( $sql );

		$table_name = $wpdb->prefix . 'crfw_cart_meta';
		$sql = "CREATE TABLE $table_name (
		            id INT(11) NOT NULL AUTO_INCREMENT,
		            cart_id INT(11) NOT NULL,
		            name VARCHAR(128) NOT NULL,
		            value TEXT,
		            PRIMARY KEY  (id),
		            KEY cart_name_idx (cart_id,name)
		        ) $charset_collate";
		dbDelta( $sql );

		// Store options to indicate we're installed and are ready to go.
		update_option( 'crfw_db_version', CRFW_DB_VERSION );
	}
	register_activation_hook( __FILE__, 'crfw_install' );


	function crfw_deactivation() {
		wp_clear_scheduled_hook( 'crfw_cron' );
	}
	register_deactivation_hook( __FILE__, 'crfw_deactivation' );

	$GLOBALS['crfw'] = new \Ademti\Crfw\Main( plugins_url( '', __FILE__ ) );
}

/**
 * Declare support for High Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
