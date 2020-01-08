<?php

/**
 * Manager Class
 */
class Cnvrsn_Integration_Manager {

	/**
	 * All integrations
	 *
	 * @var array
	 */
	private $integrations = array();

	/**
	 * Constructor for Cnvrsn_Integration_Manager Class
	 */
	public function __construct() {
		$this->includes_integration();
	}

	/**
	 * Required all integration class
	 *
	 * @return void
	 */
	public function includes_integration() {
		$this->integrations['google']       = require_once CNVRSN_INCLUDES . '/integrations/class-integration-google.php';
		$this->integrations['custom']       = require_once CNVRSN_INCLUDES . '/integrations/class-integration-custom.php';
	}

	/**
	 * Get all active integrations
	 *
	 * @return void
	 */
	public function get_active_integrations() {
		$integrations = $this->integrations;
		$active       = array();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_enabled() ) {
				$active[] = $integration;
			}
		}

		return $active;
	}

	/**
	 * Get all integration
	 *
	 * @return array
	 */
	public function get_integrations() {
		if ( empty( $this->integrations ) ) {
			return;
		}

		return $this->integrations;
	}

}
