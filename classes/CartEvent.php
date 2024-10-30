<?php

namespace Ademti\Crfw;

use Exception;

/**
 * CartEvent class. Represents a cart event.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class CartEvent {
	/**
	 * The user visible ID.
	 */
	private $id;

	// Whether the record has been modified.
	private $dirty = false;

	private $accessible_props = array(
		'cart_id',
		'type',
		'details',
		'created',
	);

	/**
	 * The internal cart ID.
	 *
	 * @var string
	 */
	private $cart_id = '';

	/**
	 * The cart event type.
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * The details associated with the event.
	 * @var string
	 */
	private $details = '';

	/**
	 * @var string
	 */
	private $created = 'pending';

	/**
	 * Constructor.
	 *
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	public function __construct( $id = null ) {
		// This is a new item. Nothing to do here.
		if ( empty( $id ) ) {
			return;
		}
		// Decode the hash, and load the data.
		$this->load( $id );
	}

	/**
	 * Magic getter.
	 *
	 * Allow access to some properties, which are otherwise set to private so we can
	 * check whether they are dirty.
	 *
	 * @param string $key The key to retrieve.
	 *
	 * @return mixed        The value of the key, or NULL;
	 */
	public function __get( $key ) {
		if ( in_array( $key, $this->accessible_props, true ) ) {
			return $this->$key;
		}
		throw new Exception(
			'Attempt to access inaccessible property (' . esc_html( $key ) . ') on ' .
			esc_html( __CLASS__ ) . ' class.'
		);
	}

	/**
	 * Magic setter.
	 *
	 * Allow access to some properties. Tracks whether items are changed so we know if
	 * we need to save or not.
	 *
	 * @param string $key The key to set.
	 *
	 * @param string $value The value to assign to the property.
	 */
	public function __set( $key, $value ) {
		if ( ! in_array( $key, $this->accessible_props, true ) ) {
			throw new Exception( 'Attempt to set inaccessible property on ' . __CLASS__ . ' class.' );
		}
		if ( $this->$key !== $value ) {
			$this->$key  = $value;
			$this->dirty = true;
		}
	}

	/**
	 * Persist the data back to the database.
	 *
	 * @return string  The hashed id.
	 */
	public function save() {
		// Nothing to do unless we're dirty.
		if ( ! $this->dirty ) {
			return $this->id;
		}
		if ( empty( $this->id ) ) {
			return $this->insert();
		} else {
			return $this->update();
		}
	}

	/**
	 * Insert a new object into the database.
	 *
	 * @return string  The ID of the record.
	 */
	private function insert() {
		global $wpdb;
		$timestamp = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res = $wpdb->insert(
			$wpdb->prefix . 'crfw_cart_event',
			array(
				'id'      => null,
				'cart_id' => $this->cart_id,
				'type'    => $this->type,
				'details' => $this->details,
				'created' => $timestamp,
			)
		);
		if ( ! $res ) {
			throw new Exception( 'Could not write cart event record.' );
		}
		$this->id      = $wpdb->insert_id;
		$this->created = $timestamp;

		return $this->id;
	}

	/**
	 * Update an existing cart event entry.
	 *
	 * @return string  The ID of the event.
	 */
	private function update() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$res = $wpdb->update(
			$wpdb->prefix . 'crfw_cart_event',
			array(
				'cart_id' => $this->cart_id,
				'type'    => $this->type,
				'details' => $this->details,
			),
			array(
				'id' => $this->id,
			)
		);
		if ( false === $res ) {
			throw new Exception( 'Could not update cart event record' . wp_json_encode( $res, JSON_PRETTY_PRINT ) );
		}

		return $this->id;
	}

	/**
	 * Load the contents of the cart event into the class from the database.
	 *
	 * @param string $id The ID.
	 *
	 * @return bool        True on success. False on failure.
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	private function load( $id ) {

		global $wpdb;

		if ( ! $id ) {
			return false;
		}
		// Try and retrieve the cart from the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cart_id,
				        type,
				        details,
				        created
				   FROM {$wpdb->prefix}crfw_cart_event
				  WHERE id = %d",
				$id
			)
		);
		if ( ! $results ) {
			return;
		}
		// If we get here we have cart data. Populate it.
		$this->cart_id = $results->cart_id;
		$this->type    = $results->type;
		$this->details = $results->details;
		$this->created = $results->created;
		// Store the ID since it is valid.
		$this->id = $id;
	}
}
