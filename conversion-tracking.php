<?php
/*
Plugin Name: WooCommerce Cnvrsn Trckng
Plugin URI: https://github.com/ryansalerno/woocommerce-cnvrsn-trckng
Description: Forked from the excellent WooCommerce Conversion Tracking plugin by Tareq Hasan (with lots of bits removed)
Version: 0.1.0
Author: Ryan (after Tareq Hasan)
License: GPL2
WC requires at least: 2.3
WC tested up to: 3.8.1
*/

/**
 * Original code Copyright (c) 2017 Tareq Hasan (email: tareq@wedevs.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convrsn_Trckng class
 *
 * @class Convrsn_Trckng The class that holds the entire Convrsn_Trckng plugin
 */
class Convrsn_Trckng {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version = '0.1.0';

	/**
	 * Holds various class instances
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Constructor for the Convrsn_Trckng class
	 *
	 * Sets up all the appropriate hooks and actions
	 * within our plugin.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
		$this->includes();
		$this->init_classes();
	}

	/**
	 * Magic getter to bypass referencing plugin.
	 *
	 * @param $prop
	 *
	 * @return mixed
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		return $this->{$prop};
	}

	/**
	 * Magic isset to bypass referencing plugin.
	 *
	 * @param $prop
	 *
	 * @return mixed
	 */
	public function __isset( $prop ) {
		return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
	}

	/**
	 * Initializes the Convrsn_Trckng() class
	 *
	 * Checks for an existing Convrsn_Trckng() instance
	 * and if it doesn't find one, creates it.
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new Convrsn_Trckng();
		}

		return $instance;
	}

	/**
	 * Include required files
	 *
	 * @return void
	 */
	public function includes() {
		require_once CNVRSN_INCLUDES . '/class-abstract-integration.php';
		require_once CNVRSN_INCLUDES . '/class-integration-manager.php';
		require_once CNVRSN_INCLUDES . '/class-event-dispatcher.php';
		require_once CNVRSN_INCLUDES . '/class-admin.php';
	}

	/**
	 * Define the constants
	 *
	 * @since 1.2.5
	 *
	 * @return void
	 */
	public function define_constants() {
		define( 'CNVRSN_FILE', __FILE__ );
		define( 'CNVRSN_PATH', dirname( CNVRSN_FILE ) );
		define( 'CNVRSN_INCLUDES', CNVRSN_PATH . '/includes' );
	}

	/**
	 * Initialize the hooks
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_notices', array( $this, 'check_woocommerce_exist' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Instantiate the required classes
	 *
	 * @return void
	 */
	public function init_classes() {
		$this->container['event_dispatcher'] = new Cnvrsn_Event_Dispatcher();
		$this->container['admin']            = new Cnvrsn_Admin();
		$this->container['manager']          = new Cnvrsn_Integration_Manager();
	}

	/**
	 * Plugin action links
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	function plugin_action_links( $links ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=conversion-tracking' ) . '">' . __( 'Settings', 'woocommerce-cnvrsn-trckng' ) . '</a>';

		return $links;
	}

	/**
	 * Check Woocommerce exists
	 *
	 * @return void
	 */
	public function check_woocommerce_exist() {
		if ( ! function_exists( 'WC' ) ) {
			?>
				<div class="error notice is-dismissible">
					<p><?php echo wp_kses_post( __( '<b>Woocommerce conversion tracking</b> requires <a target="_blank" href="https://wordpress.org/plugins/woocommerce/">Woocommerce</a>', 'woocommerce-cnvrsn-trckng' ) );?></p>
				</div>
			<?php
		}
	}
}

function cnvrsn_init() {
	return Convrsn_Trckng::init();
}

// Convrsn_Trckng
cnvrsn_init();

/**
 * Manage Capability
 *
 * @return void
 */
function cnvrsn_manage_cap() {
	return apply_filters( 'cnvrsn_capability', 'manage_options' );
}
