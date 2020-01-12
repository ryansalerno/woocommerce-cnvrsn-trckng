<?php
/**
 * Manage available integrations
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng;

/**
 * Load and initialize our Integrations, and return info about them
 */
class IntegrationManager {
	/**
	 * All integrations
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private static $integrations = array();

	/**
	 * Active integrations
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private static $active = array();

	/**
	 * Require all our integration classes
	 *
	 * @since 0.1.0
	 */
	public static function load_integrations() {
		require_once __DIR__ . '/integrations/_abstract.php';
		require_once __DIR__ . '/integrations/custom.php';
		require_once __DIR__ . '/integrations/google-ads.php';

		self::$integrations['google-ads'] = new GoogleAdsIntegration();
		self::$integrations['custom']     = new CustomIntegration();

		self::check_active_integrations();
	}

	/**
	 * Check settings to find out which integrations are active
	 *
	 * @return void
	 * @since 0.1.0
	 */
	protected static function check_active_integrations() {
		foreach ( self::$integrations as $integration ) {
			if ( $integration->is_enabled() ) {
				self::$active[] = $integration;
			}
		}
	}

	/**
	 * Get only active integrations
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public static function active_integrations() {
		return self::$active;
	}

	/**
	 * Get our integrations
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public static function all_integrations() {
		return self::$integrations;
	}
}

IntegrationManager::load_integrations();
