<?php
/**
 * Integration Abstract
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng;

/**
 * Define a minimum viable integration off which real integrations will be based
 */
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
	 * Set up our integration
	 */
	public function __construct() {
		$this->add_settings_fields();

		if ( isset( $this->asyncs ) || isset( $this->defers ) ) {
			add_filter( 'script_loader_tag', array( $this, 'asyncdefer_script' ), 10, 2 );
		}
	}

	/**
	 * Add any custom settings
	 *
	 * @since 0.1.0
	 */
	protected function add_settings_fields() {
		add_action( 'cnvrsn_trckng_' . $this->id . '_render_settings_first', array( $this, 'add_settings_fields_first' ) );
		add_action( 'cnvrsn_trckng_' . $this->id . '_render_settings_last', array( $this, 'add_settings_fields_last' ) );

		add_filter( 'cnvrsn_trckng_sanitize_settings', array( $this, 'sanitize_settings_fields' ), 10, 2 );
	}

	/**
	 * Settings Fields key (for convenience, because it's long)
	 *
	 * @param  string $suffix Additional key text to append to our too-long base
	 * @return string
	 * @since 0.1.0
	 */
	public function settings_key( $suffix ) {
		return 'cnvrsn_trckng_settings[integrations][' . $this->id . ']' . $suffix;
	}

	/**
	 * Add any custom settings before the events list
	 *
	 * @since 0.1.0
	 */
	public function add_settings_fields_first() {}

	/**
	 * Add any custom settings after the events list
	 *
	 * @since 0.1.0
	 */
	public function add_settings_fields_last() {}

	/**
	 * For any added settings, we must have a sanitizer function or they won't be saved
	 *
	 * @param  array $cleaned An empty or previously-saved array of settings
	 * @param  array $saved Untrusted user-input we must sanitize before actually saving
	 * @since 0.1.0
	 */
	public function sanitize_settings_fields( $cleaned, $saved ) {
		return $cleaned;
	}

	/**
	 * Enqueue necessary scripts
	 *
	 * @return void
	 * @since 0.1.0
	 */
	public function enqueue_script() {}

	/**
	 * Add async/defer to our enqueues for great justice
	 *
	 * @param  string $tag The script tag to be output to the page
	 * @param  string $handle An enqueued handle whose performance we'd like to optimize
	 * @return string $tag Our possibly filtered script tag
	 * @since 0.1.0
	 */
	public function asyncdefer_script( $tag, $handle ) {
		if ( ! empty( $this->defers ) && in_array( $handle, $this->defers, true ) ) {
			$tag = str_replace( '<script ', '<script defer ', $tag );
		}

		if ( ! empty( $this->asyncs ) && in_array( $handle, $this->asyncs, true ) ) {
			$tag = str_replace( '<script ', '<script async ', $tag );
		}

		return $tag;
	}

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
		$integration_events = apply_filters( 'cnvrsn_trckng_' . $this->id . '_supported_events', $this->events );
		$supported_events   = array_keys( Events\supported_events() );

		return array_intersect( $supported_events, $integration_events );
	}

	/**
	 * Check if the integration is enabled
	 *
	 * @return boolean
	 * @since 0.1.0
	 */
	public function is_enabled() {
		return $this->get_plugin_settings( 'enabled' );
	}

	/**
	 * Check if an event is enabled in the plugin settings
	 *
	 * @param  string $event An event type slug
	 * @return boolean
	 * @since 0.1.0
	 */
	public function event_enabled( $event ) {
		$events = $this->get_plugin_settings( 'events' );

		if ( ! $events || ! isset( $events[ $event ] ) ) { return false; }

		return $events[ $event ];
	}

	/**
	 * Get settings from the plugin
	 *
	 * @param  string $key Optional sub-key to pluck out of our array
	 * @return mixed|false
	 * @since 0.1.0
	 */
	protected function get_plugin_settings( $key = '' ) {
		$settings = Admin\get_settings();

		if ( ! isset( $settings['integrations'][ $this->id ] ) ) { return false; }

		if ( $key ) {
			return isset( $settings['integrations'][ $this->id ][ $key ] ) ? $settings['integrations'][ $this->id ][ $key ] : false;
		}

		return $settings['integrations'][ $this->id ];
	}

	/**
	 * Check if this integration supports a specific event
	 *
	 * @param  array $event An event type slug
	 * @return boolean
	 * @since 0.1.0
	 */
	public function supports( $event ) {
		return in_array( $event, $this->events, true );
	}

	/**
	 * Filter codes for dynamic data replacement
	 *
	 * @param  string $code Some user-entered code that needs replacements
	 * @param  array  $data Array of data for potentially making replacements
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
