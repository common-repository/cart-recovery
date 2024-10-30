<?php

namespace Ademti\Crfw;

use Exception;
use Hashids\Hashids;
use Ademti\Crfw\CartEvent;

/**
 * Cart class. Represents a cart.
 */
class Cart {

	// The user visible ID.
	protected $hashed_id;

	// The actual ID.
	protected $cart_id;

	// Whether the record has been modified.
	protected $dirty = false;

	protected $accessible_props = array(
		'email',
		'status',
		'cart_details',
		'first_name',
		'surname',
		'updated',
		'completed',
		'hashed_id',
	);

	// The main data.
	protected $email = '';

	protected $first_name = '';

	protected $surname = '';

	protected $status = 'pending';

	protected $cart_details = array();

	protected $created = 0;

	protected $updated = 0;

	protected $completed = 0;

	/**
	 * Constructor.
	 */
	public function __construct( $cart_id = null ) {
		// This is a new item. Nothing to do here.
		if ( empty( $cart_id ) ) {
			$this->created = time();
			$this->updated = time();

			return;
		}
		// Decode the hash, and load the data.
		$this->load( $cart_id );
	}

	/**
	 * Magic getter.
	 *
	 * Allow access to some properties, which are otherwise set to protected so we can
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
		if ( 'cart_id' === $key ) {
			return $this->cart_id;
		}
		throw new Exception( 'Attempt to access inaccessible property on Cart class.' );
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
			throw new Exception( 'Attempt to set inaccessible property on Cart class.' );
		}
		if ( $this->$key !== $value ) {
			$this->$key  = $value;
			$this->dirty = true;
		}
	}

	/**
	 * Magic isset() checker.
	 */
	public function __isset( $key ) {
		if ( ! in_array( $key, $this->accessible_props, true ) && 'cart_id' !== $key ) {
			throw new Exception( 'Attempt to set inaccessible property on Cart class.' );
		}

		return isset( $this->$key );
	}

	/**
	 * Persist the data back to the database.
	 *
	 * @return string  The hashed id.
	 */
	public function save() {
		// Nothing to do unless we're dirty.
		if ( ! $this->dirty ) {
			return $this->hashed_id;
		}
		if ( empty( $this->cart_id ) ) {
			return $this->insert();
		} else {
			return $this->update();
		}
	}

	/**
	 * Triggered when we know that campaigns can be activated.
	 *
	 * Should only ever be called once per install when we first notice that
	 * cart completion tracking is working.
	 *
	 * @TODO This should have a better home, and use the template loader.
	 */
	private function send_enablement_email() {
		global $crfw;
		$settings_url       = admin_url( 'admin.php?page=cart_recovery_for_wordpress&tab=main' );
		$message            = '<p>' . __( "Congratulations, we've confirmed that we can track completed purchases. Now that's sorted, you're free to enable your campaigns whenever you're ready.", 'crfw' ) . '</p>';
		$message           .= '<p><center><a style="box-sizing: border-box; font-size: 14px; color: #fff; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; border-radius: 5px; text-transform: capitalize; background-color: #348eda; margin: 0; border-color: #348eda; border-style: solid; border-width: 10px 20px; margin: 2em 0;" href="' . esc_attr( $settings_url ) . '">';
		$message           .= __( 'Enable campaigns now', 'crfw' );
		$message           .= '</a></center>';
		$notification_email = new NotificationEmail(
			$crfw->get_settings(),
			__( 'Your campaign is almost ready to go', 'crfw' ),
			$message
		);
		$notification_email->send();
	}

	/**
	 * Update the status to a relevant value when a cart has been completed.
	 *
	 * NOTE: Does not persist changes, the caller is responsible for calling
	 * save() as well to save the status update to the database.
	 *
	 * @param float $order_value The value of the order completing the cart.
	 */
	public function set_completed( $order_value = 0 ) {
		// Note that cart completion tracking is working.
		if ( update_option( 'crfw_cart_completion_working', true ) ) {
			$this->send_enablement_email();
		}

		// Get the new cart status for the item. Bail if no change.
		$new_status = $this->get_completed_status();
		if ( $new_status ) {
			$this->status = $new_status;
			if ( empty( $this->completed ) ) {
				$this->completed = time();
			}
			$this->update_meta( 'order_value', $order_value );
			$this->dirty = true;
			do_action( 'crfw_cart_completed', $this );
		}
	}

	/**
	 * Put cart into recovery process.
	 *
	 * @param int $pending_duration The number of seconds the cart was pending for.
	 */
	public function put_in_recovery( $pending_duration ) {
		$this->status = 'recovery';
		$this->dirty  = true;

		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $this->cart_id;
		$event->type    = 'neutral';
		$event->details = __( 'Cart entered recovery process.', 'cart-recovery' );
		$event->save();

		// Store the time that the cart was eligible to go into recovery.
		$this->add_meta( 'recovery_started', $this->updated + $pending_duration );
		do_action( 'crfw_cart_entered_recovery', $this );
	}

	/**
	 * Load by the public hashed ID.
	 *
	 * @param int $hashed_id The public hashed ID.
	 */
	public function load_by_hash( $hashed_id ) {
		$cart_id = $this->decode( $hashed_id );
		$this->load( $cart_id );

		return $this->hashed_id;
	}

	/**
	 * Load based on an email address.
	 *
	 * Matches carts in empty, pending, or recovery status.
	 */
	public function load_by_email( $email ) {
		global $wpdb;
		// Look for a cart for this email in the right status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				          FROM %i
				         WHERE email = %s
				           AND status IN ( 'empty', 'pending', 'recovery' )
				         LIMIT 1",
				$wpdb->prefix . 'crfw_cart',
				$email
			)
		);
		if ( ! empty( $cart_id ) ) {
			$this->load( $cart_id );
		}
	}

	/**
	 * Add a meta record for this cart.
	 *
	 * @param string $name The meta name.
	 * @param string $value The meta value to store.
	 */
	public function add_meta( $name, $value ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->prefix . 'crfw_cart_meta',
			[
				'id'      => null,
				'cart_id' => $this->cart_id,
				'name'    => $name,
				'value'   => $value,
			]
		);
	}

	/**
	 * @param $name
	 *
	 * @return void
	 */
	public function delete_meta( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'crfw_cart_meta',
			[
				'cart_id' => $this->cart_id,
				'name'    => $name,
			],
			[
				'%d',
				'%s',
			]
		);
	}

	/**
	 * Update the value of a meta record for this cart.
	 *
	 * Will add a record if none exists.
	 *
	 * @param string $name The meta name.
	 * @param string $value The meta value to store.
	 */
	public function update_meta( $name, $value ) {
		global $wpdb;

		if ( $this->has_meta( $name ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'crfw_cart_meta',
				[ 'value' => $value ],
				[
					'name'    => $name,
					'cart_id' => $this->cart_id,
				],
				[ '%s' ],
				[ '%s', '%d' ]
			);
		} else {
			$this->add_meta( $name, $value );
		}
	}

	/**
	 * Determine if this cart has a meta entry.
	 *
	 * @param string $name The meta name to check.
	 *
	 * @return boolean       True if the cart has the meta, false otherwise.
	 */
	public function has_meta( $name ) {
		return $this->get_meta( $name ) !== null;
	}

	/**
	 * Retrieve a meta record for this cart.
	 *
	 * @param string $name The key of the meta to retrieve.
	 *
	 * @return string        The meta value, or NULL.
	 */
	public function get_meta( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT `value`
				            FROM %i
						   WHERE cart_id = %d
						     AND name = %s',
				$wpdb->prefix . 'crfw_cart_meta',
				$this->cart_id,
				$name
			)
		);
	}

	/**
	 * Unsubscribe a cart from the recovery process.
	 */
	public function unsubscribe() {
		// Only applies if it is in recovery.
		if ( 'recovery' !== $this->status ) {
			return;
		}
		// Update the status.
		$this->status = 'unrecovered';
		// Record meta.
		$this->add_meta( 'user_unsubscribe', time() );
		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $this->cart_id;
		$event->type    = 'negative';
		$event->details = __( 'User unsubscribed from emails.', 'cart-recovery' );
		$event->save();
		$this->dirty = true;
		do_action( 'crfw_cart_unsubscribed', $this );
	}

	/**
	 * Mark a cart as unrecovered with a meta / message pair.
	 */
	public function unrecovered( $meta = 'cart_unrecovered', $message = null ) {
		if ( is_null( $message ) ) {
			$message = __( 'Cart marked as unrecovered.', 'cart-recovery' );
		}
		// Only applies if it is in recovery.
		if ( 'recovery' !== $this->status ) {
			return;
		}
		// Update the status.
		$this->status = 'unrecovered';
		// Record meta.
		$this->add_meta( $meta, time() );
		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $this->cart_id;
		$event->type    = 'negative';
		$event->details = $message;
		$event->save();
		$this->dirty = true;
		do_action( 'crfw_cart_unrecovered', $this );
	}

	/**
	 * Anonymise a cart record, and stop recovery if it is in progress.
	 */
	public function anonymise() {
		// Remove the email & name.
		$this->email      = __( 'Email removed', 'cart-recovery' );
		$this->first_name = __( 'Name removed', 'cart-recovery' );
		$this->surname    = '';

		// Record meta to show when the cart was anonymised.
		$this->add_meta( 'cart_anonymised', time() );

		// Remove the user ID if it is stored in the meta table.
		$this->delete_meta( 'user_id' );

		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $this->cart_id;
		$event->type    = 'neutral';
		$event->details = __( 'Cart data anonymised', 'cart-recovery' );
		$event->save();
		$this->dirty = true;

		// Update the status to take it out of recovery if required.
		if ( 'recovery' === $this->status ) {
			$this->add_meta( 'cart_unrecovered', time() );
			$this->status = 'unrecovered';
			// Log event.
			$event          = new CartEvent();
			$event->cart_id = $this->cart_id;
			$event->type    = 'negative';
			$event->details = __( 'Cart anonymised. Recovery stopped.', 'cart-recovery' );
			$event->save();
		}
		do_action( 'crfw_cart_anonymised', $this );
	}

	/**
	 * Insert a new object into the database.
	 *
	 * Set the cart_id, hashed_id and return the hashed_id.
	 * @return string  The hashed ID.
	 */
	protected function insert() {
		global $wpdb;
		$timestamp = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$res           = $wpdb->insert(
			$wpdb->prefix . 'crfw_cart',
			[
				'id'           => null,
				'email'        => $this->email,
				'first_name'   => $this->first_name,
				'surname'      => $this->surname,
				'status'       => $this->status,
				'cart_details' => serialize( $this->cart_details ),
				'updated'      => $timestamp,
				'created'      => $timestamp,
			]
		);
		$this->updated = $timestamp;
		$this->created = $timestamp;
		if ( ! $res ) {
			throw new Exception( 'Could not write cart record.' );
		}
		$this->cart_id   = $wpdb->insert_id;
		$this->hashed_id = $this->encode( $this->cart_id );

		// Log event.
		$event          = new CartEvent();
		$event->cart_id = $this->cart_id;
		$event->type    = 'positive';
		$event->details = __( 'Cart details captured.', 'cart-recovery' );
		$event->save();
		do_action( 'crfw_cart_captured', $this );

		return $this->hashed_id;
	}

	/**
	 * Update an existing cart entry.
	 *
	 * @return string  The hashed ID.
	 */
	protected function update() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$res = $wpdb->update(
			$wpdb->prefix . 'crfw_cart',
			array(
				'email'        => $this->email,
				'first_name'   => $this->first_name,
				'surname'      => $this->surname,
				'status'       => $this->status,
				'updated'      => $this->updated,
				'completed'    => $this->completed,
				'cart_details' => serialize( $this->cart_details ),
			),
			array(
				'id' => $this->cart_id,
			)
		);
		if ( false === $res ) {
			throw new Exception( 'Could not update cart record' );
		}
		do_action( 'crfw_cart_updated', $this );

		return $this->hashed_id;
	}

	/**
	 * Work out which status a cart should be updated to when completed.
	 *
	 * @return string         The target status.
	 */
	protected function get_completed_status() {
		// If it was completed from pending, then it's a normal purchase. Flag as completed.
		if ( 'pending' === $this->status ) {
			return 'completed';
			// If it was in recovery then we have recovered it!
		} elseif ( 'recovery' === $this->status || 'unrecovered' === $this->status ) {
			return 'recovered';
			// Should not happen, leave status as-is.
		} else {
			return false;
		}
	}

	/**
	 * Load the contents of the cart into the class from the database.
	 *
	 * @param string $cart_id The internal ID
	 *
	 * @return bool             True on success. False on failure.
	 */
	protected function load( $cart_id ) {

		global $wpdb;

		if ( ! $cart_id ) {
			return false;
		}
		// Try and retrieve the cart from the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT email,
						first_name,
						surname,
				        status,
				        cart_details,
				        created,
				        updated
				   FROM %i
				  WHERE id = %d',
				$wpdb->prefix . 'crfw_cart',
				$cart_id
			)
		);
		if ( ! $results ) {
			return false;
		}
		// If we get here we have cart data. Populate it.
		$this->cart_id      = $cart_id;
		$this->email        = $results->email;
		$this->first_name   = $results->first_name;
		$this->surname      = $results->surname;
		$this->status       = $results->status;
		$this->cart_details = unserialize( $results->cart_details );
		$this->created      = $results->created;
		$this->updated      = $results->updated;
		// Store the IDs since they are valid.
		$this->hashed_id = $this->encode( $cart_id );
	}

	/**
	 * Decode a value
	 *
	 * @param string $hashed_id The hash.
	 *
	 * @return string|bool             The decoded value, or false.
	 */
	protected function decode( $hashed_string ) {
		$hashids = new hashids( $this->get_salt() );
		$decoded = $hashids->decode( $hashed_string );
		if ( ! $decoded ) {
			return false;
		} else {
			return $decoded[0];
		}
	}

	/**
	 * Encode a value.
	 *
	 * @param string $raw_string The raw string to encode.
	 *
	 * @return string              The encoded string.
	 */
	protected function encode( $raw_string ) {
		$hashids = new hashids( $this->get_salt() );
		$encoded = $hashids->encode( $raw_string );
		if ( ! $encoded ) {
			return false;
		} else {
			return $encoded;
		}
	}

	/**
	 * Retrieve, or generate the salt for the hash IDs.
	 *
	 * @return string  The sale to use.
	 */
	protected function get_salt() {
		$salt = get_option( 'crfw_cart_salt' );
		if ( ! $salt ) {
			$salt = md5( time() . random_int( 0, 65535 ) );
			add_option( 'crfw_cart_salt', $salt );
		}

		return $salt;
	}
}
