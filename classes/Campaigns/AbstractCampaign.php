<?php

namespace Ademti\Crfw\Campaigns;

use Ademti\Crfw\Settings;

abstract class AbstractCampaign {

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var string
	 */
	protected $label;

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @var mixed
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * Store the base URL, and add hooks.
	 */
	public function __construct( $settings, $data ) {
		// Store the settings.
		$this->data     = $data;
		$this->settings = $settings;
		$this->init();
		add_action( 'crfw_run_campaigns', array( $this, 'run_campaign' ) );
	}

	/**
	 * Return the slug for this campaign.
	 * @return string The slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Return a user-visible label to be used for this campaign.
	 * @return string The label.
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Do whatever is required to run a campaign.
	 */
	abstract public function run_campaign();
}
