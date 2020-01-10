<?php

/**
 * Integration Abstract
 */
namespace CnvrsnTrckng;

abstract class Integration {
	/**
	 * ID/Slug
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected $id;

	/**
	 * Display name
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected $name;

	/**
	 * Supported events
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $events = array();

	/**
	 * Get settings fields
	 *
	 * @return array
	 * @since 0.1.0
	 */
	abstract public function get_settings_fields();

	/**
	 * Enqueue necessary scripts
	 *
	 * @return void
	 * @since 0.1.0
	 */
	abstract public function enqueue_script();

	/**
	 * Get the ID/slug
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the display name
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get the supported events
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function get_events() {
		return apply_filters( 'cnvrsn_trckng_' . $this->id . '_supported_events', $this->events );
	}

	/**
	 * Check if the integration is enabled
	 *
	 * @return boolean
	 * @since 0.1.0
	 */
	public function is_enabled() {
		$settings = $this->get_plugin_settings();

		if ( $settings && $settings[ 'enabled' ] == true ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an event is enabled in the plugin settings
	 *
	 * @param  string $event
	 * @return boolean
	 * @since 0.1.0
	 */
	public function event_enabled( $event ) {
		$settings = $this->get_plugin_settings();

		if ( isset( $settings[0]['events'] ) && array_key_exists( $event, $settings[0]['events'] ) && $settings[0]['events'][ $event ] == 'on' ) {
			return true;
		}

		return false;
	}

	/**
	 * Get settings from the plugin
	 *
	 * @param  string $integration_id
	 * @return array|false
	 * @since 0.1.0
	 */
	protected function get_plugin_settings() {
		$integration_settings = get_option( 'cnvrsn_settings', array() );

		if ( isset( $integration_settings[ $this->id ] ) ) {
			return $integration_settings[ $this->id ];
		}

		return false;
	}

	/**
	 * Check if this integration supports a specific event
	 *
	 * @param  array $feature
	 * @return boolean
	 * @since 0.1.0
	 */
	public function supports( $feature ) {
		return in_array( $feature, $this->events );
	}

	/**
	 * Filter codes for dynamic data replacement
	 *
	 * @param  string $code
	 * @param  array $data
	 * @return string
	 * @since 0.1.0
	 */
	protected function dynamic_data_replacement( $code, $data ) {
		foreach ( (array) $data as $key => $value ) {
			if ( empty( $value ) ) { continue; }

			$code = str_replace( '{' . $key . '}', $value, $code );
		}

		return $code;
	}
}
