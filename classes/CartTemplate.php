<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Cart;
use Ademti\Crfw\Settings;
use Ademti\Crfw\TemplateLoader;

/**
 * Cart class. Represents a cart.
 */
class CartTemplate {

	private $cart;

	private $settings;

	private $tags = array();

	private $template_loader;

	private $extra;

	/**
	 * Constructor.
	 *
	 * Store all data for future use.
	 *
	 * @param Cart     $cart The cart being processed.
	 * @param Settings $settings The general settings object.
	 * @param string   $subject The subject for the email.
	 * @param mixed    $extra Additional information to be available to callbacks.
	 */
	public function __construct( Cart $cart, Settings $settings, $subject = '', $extra = null ) {
		$this->cart     = $cart;
		$this->settings = $settings;
		$this->subject  = $subject;
		$this->extra    = $extra;
		$this->tags     = apply_filters(
			'crfw_cart_template_tags',
			array(
				'cart'        => array(
					'callback'    => array( $this, 'replace_cart' ),
					'description' => __(
						"A tabular cart showing each of the items in the customer's cart",
						'cart-recovery'
					),
					'context'     => array( 'body' ),
				),
				'cart_url'    => array(
					'callback'    => array( $this, 'replace_cart_url' ),
					'description' => __(
						'A URL that will take the customer back to the checkout with their cart populated',
						'cart-recovery'
					),
					'context'     => array( 'body' ),
				),
				'cart_button' => array(
					'callback'    => array( $this, 'replace_cart_button' ),
					'description' => __( "A clickable button linking back to the customer's cart", 'cart-recovery' ),
					'context'     => array( 'body' ),
				),
				'last_name'   => array(
					'callback'    => array( $this, 'replace_last_name' ),
					'description' => __( "The customer's last name if available. Blank if not.", 'cart-recovery' ),
					'context'     => array( 'body', 'subject' ),
				),
				'first_name'  => array(
					'callback'    => array( $this, 'replace_first_name' ),
					'description' => sprintf(
					// translators: %s is the fallback term used in place of the customer's name.
						__( "The customer's first name if available. '%s' if not", 'cart-recovery' ),
						_x( 'friend', 'Used as a first name if none is available.', 'cart-recovery' )
					),
					'context'     => array( 'body', 'subject' ),
				),
				'store_name'  => array(
					'callback'    => array( $this, 'replace_store_name' ),
					'description' => __( 'The name of the store', 'cart-recovery' ),
					'context'     => array( 'body', 'subject' ),
				),
				'store_email' => array(
					'callback'    => array( $this, 'replace_store_email' ),
					'description' => __( 'The email address of the store', 'cart-recovery' ),
					'context'     => array( 'body', 'subject' ),
				),
				'unsub_link'  => array(
					'callback'    => array( $this, 'replace_unsub_link' ),
					'description' => __(
						'A URL where the customer can unsubscribe from the campaign',
						'cart-recovery'
					),
					'context'     => array( 'body' ),
				),
				'subject'     => array(
					'callback'    => array( $this, 'replace_subject' ),
					'description' => __( 'The subject line of the email', 'cart-recovery' ),
					'context'     => array( 'body' ),
				),
			)
		);

		$this->template_loader = new TemplateLoader();
	}

	/**
	 * Renders a list of tags that can be used in the email.
	 */
	public function get_email_tag_list() {
		$tag_list = '';
		foreach ( $this->tags as $tag => $tag_info ) {
			if ( empty( $tag_info['description'] ) ) {
				// Fallback for pro add-on prior to change in tag registration.
				$description = '{' . $tag . '} tag';
			} else {
				$description = $tag_info['description'];
			}
			$variables = array(
				'tag'  => esc_html( $tag ),
				'desc' => esc_html( $description ),
			);
			$tag_list .= $this->template_loader->get_template_with_variables(
				'admin',
				'email-tag-list-item',
				$variables
			);
		}
		$variables = array( 'tag_list' => $tag_list );

		return $this->template_loader->get_template_with_variables( 'admin', 'email-tag-list', $variables );
	}

	/**
	 * Replace template tags in the input string based on the cart contents.
	 *
	 * @param string $original_string The input string.
	 * @param string $context Whether we are replacing for the body or subject of the email.
	 *
	 * @return string          The string with tags replaced.
	 */
	public function replace( $original_string, $context = 'body' ) {
		foreach ( $this->tags as $tag => $tag_info ) {
			if ( stripos( $original_string, '{' . $tag . '}' ) !== false ) {
				if ( empty( $tag_info['callback'] ) ) {
					// Fallback for pro add-on prior to change in tag registration.
					$callback = $tag_info;
				} else {
					$callback = $tag_info['callback'];
				}
				if ( empty( $tag_info['context'] ) || in_array( $context, $tag_info['context'], true ) ) {
					$original_string = str_replace(
						'{' . $tag . '}',
						$callback( $this->cart, $this->settings, $this->extra ),
						$original_string
					);
				}
			}
		}

		return $original_string;
	}

	/**
	 * Deal with the {first_name} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_first_name( $cart, $settings, $extra ) {
		$name = $cart->first_name;
		if ( ! empty( $name ) ) {
			return $name;
		} else {
			return _x( 'friend', 'Used as a first name if none is available.', 'cart-recovery' );
		}
	}

	/**
	 * Deal with the {surname} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_last_name( $cart, $settings, $extra ) {
		$name = $cart->surname;
		if ( ! empty( $name ) ) {
			return $name;
		} else {
			return '';
		}
	}

	/**
	 * Deal with the {store_name} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_store_name( $cart, $settings, $extra ) {

		$name = get_bloginfo( 'name' );
		if ( ! empty( $name ) ) {
			return $name;
		} else {
			return '';
		}
	}

	/**
	 * Deal with the {store_email} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_store_email( $cart, $settings, $extra ) {
		$email = get_bloginfo( 'admin_email' );
		if ( ! empty( $email ) ) {
			return $email;
		} else {
			return '';
		}
	}

	/**
	 * Deal with the {cart} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_cart( $cart, $settings, $extra ) {

		// Handle (legacy) situation where cart details can be empty on
		// carts in recovery.
		if ( empty( $cart->cart_details ) ) {
			return '';
		}

		$output  = '';
		$output .= $this->template_loader->get_template_with_variables( 'email', 'cart-header', array() );
		// Loop over first and see if we're going to output an image column.
		$has_images = false;
		foreach ( $cart->cart_details['contents'] as $item ) {
			if ( ! empty( $item['image'] ) ) {
				$has_images = true;
				break;
			}
		}
		// Output the cart rows
		foreach ( $cart->cart_details['contents'] as $item ) {
			$variables             = array();
			$variables['name']     = $item['name'];
			$variables['price']    = $cart->cart_details['currency_symbol'] . ' ';
			$variables['price']   .= sprintf( '%.2f', $item['price'] );
			$variables['quantity'] = $item['quantity'];
			if ( $has_images && ! empty( $item['image'] ) ) {
				$image_vars           = [];
				$image_vars['image']  = $item['image'];
				$image_vars['name']   = $variables['name'];
				$image_vars['width']  = apply_filters( 'crfw_email_image_width', 64 );
				$image_vars['height'] = apply_filters( 'crfw_email_image_height', 64 );
				$variables['image']   = $this->template_loader->get_template_with_variables(
					'email',
					'cart-row-image',
					$image_vars
				);
			} elseif ( $has_images ) {
				$variables['image'] = $this->template_loader->get_template(
					'email',
					'cart-row-empty-image-cell'
				);
			} else {
				$variables['image'] = '';
			}
			$output .= $this->template_loader->get_template_with_variables( 'email', 'cart-row', $variables );
		}

		$output .= $this->template_loader->get_template_with_variables( 'email', 'cart-footer', array() );

		return $output;
	}

	/**
	 * Deal with the {cart_url} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_cart_url( $cart, $settings, $extra ) {
		$url = $this->get_checkout_url( $cart, $settings, $extra );

		return esc_attr( $url );
	}

	/**
	 * Deal with the {cart_button} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_cart_button( $cart, $settings, $extra ) {
		$variables = array(
			'cart_url' => $this->get_checkout_url( $cart, $settings, $extra ),
		);

		return $this->template_loader->get_template_with_variables( 'email', 'cart-button', $variables );
	}

	/**
	 * Deal with the {unsub_link} tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_unsub_link( $cart, $settings, $extra ) {
		$url = home_url();

		return add_query_arg(
			array(
				'crfw_cart_hash' => $cart->hashed_id,
				'crfw_email'     => $cart->email,
				'crfw_action'    => 'unsubscribe',
			),
			$url
		);
	}

	/**
	 * Replace the subject tag.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function replace_subject( $cart, $settings, $extra ) {
		return $this->replace( $this->subject, 'subject' );
	}

	/**
	 * Generate a checkout URL for a given cart.
	 */
	private function get_checkout_url( $cart, $settings, $extra ) {
		$url = add_query_arg(
			array(
				'crfw_cart_hash' => $cart->hashed_id,
				'crfw_email'     => rawurlencode( $cart->email ),
				'crfw_action'    => 'checkout',
			),
			$settings->engine->get_checkout_url()
		);

		return apply_filters( 'crfw_checkout_url', $url, $cart, $extra );
	}
}
