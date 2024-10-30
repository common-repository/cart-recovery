<?php

namespace Ademti\Crfw;

use Ademti\Crfw\Settings;
use Ademti\Crfw\TemplateLoader;

/**
 * Notification email class.
 *
 * Used to send notification emails to the site admin.
 */
class NotificationEmail {

	private $template_loader;
	private $settings;
	private $message;

	/**
	 * Constructor.
	 *
	 * Store all data for future use.
	 *
	 * @param Settings $settings The general settings object.
	 * @param string $subject The subject for the email.
	 * @param string $message The message to go into the email.
	 */
	public function __construct( Settings $settings, $subject = '', $message = '' ) {
		$this->settings        = $settings;
		$this->subject         = $subject;
		$this->message         = $message;
		$this->template_loader = new TemplateLoader();
	}

	public function send() {
		// Parse the subject and message into the notification template.
		$message = $this->template_loader->get_template_with_variables(
			'admin',
			'notification-email',
			array(
				'subject' => $this->subject,
				'message' => $this->message,
			)
		);
		// Wrap the notification email content in the standard wrapper.
		$message = $this->template_loader->get_template_with_variables(
			'email',
			'html-wrapper',
			array(
				'message' => $message,
			)
		);

		// Send it to the "From" campaign address.
		return $this->settings->engine->mail(
			$this->settings->crfw_email_from_address,
			$this->subject,
			$message
		);
	}
}
