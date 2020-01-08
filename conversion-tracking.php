<?php
/*
Plugin Name: WooCommerce Conversion Tracking
Plugin URI: https://wedevs.com/products/plugins/woocommerce-conversion-tracking/
Description: Adds various conversion tracking codes to cart, checkout, registration success and product page on WooCommerce
Version: 2.0.6
Author: Tareq Hasan
Author URI: https://tareq.co/
License: GPL2
WC requires at least: 2.3
WC tested up to: 3.8.1
*/

/**
 * Copyright (c) 2017 Tareq Hasan (email: tareq@wedevs.com). All rights reserved.
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
 * WeDevs_WC_Facebook_Tracking_Pixel class
 *
 * @class WeDevs_WC_Facebook_Tracking_Pixel The class that holds the entire WeDevs_WC_Facebook_Tracking_Pixel plugin
 */
class WeDevs_WC_Conversion_Tracking {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '2.0.6';

    /**
     * Holds various class instances
     *
     * @var array
     */
    private $container = array();

    /**
     * Constructor for the WeDevs_WC_Conversion_Tracking class
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
     * Initializes the WeDevs_WC_Conversion_Tracking() class
     *
     * Checks for an existing WeDevs_WC_Conversion_Tracking() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new WeDevs_WC_Conversion_Tracking();
        }

        return $instance;
    }

    /**
     * Include required files
     *
     * @return void
     */
    public function includes() {
        require_once WCCT_INCLUDES . '/class-abstract-integration.php';
        require_once WCCT_INCLUDES . '/class-integration-manager.php';
        require_once WCCT_INCLUDES . '/class-event-dispatcher.php';
        require_once WCCT_INCLUDES . '/class-admin.php';
    }

    /**
     * Define the constants
     *
     * @since 1.2.5
     *
     * @return void
     */
    public function define_constants() {
        define( 'WCCT_FILE', __FILE__ );
        define( 'WCCT_PATH', dirname( WCCT_FILE ) );
        define( 'WCCT_INCLUDES', WCCT_PATH . '/includes' );
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
        $this->container['event_dispatcher'] = new WCCT_Event_Dispatcher();
        $this->container['admin']            = new WCCT_Admin();
        $this->container['manager']          = new WCCT_Integration_Manager();
    }

    /**
     * Plugin action links
     *
     * @param  array $links
     *
     * @return array
     */
    function plugin_action_links( $links ) {
        $links[] = '<a href="' . admin_url( 'admin.php?page=conversion-tracking' ) . '">' . __( 'Settings', 'woocommerce-conversion-tracking' ) . '</a>';

        return $links;
    }
    /**
     * Check Woocommerce exist
     *
     * @return void
     */
    public function check_woocommerce_exist() {
        if ( ! function_exists( 'WC' ) ) {
            ?>
                <div class="error notice is-dismissible">
                    <p><?php echo wp_kses_post( __( '<b>Woocommerce conversion tracking</b> requires <a target="_blank" href="https://wordpress.org/plugins/woocommerce/">Woocommerce</a>', 'woocommerce-conversion-tracking' ) );?></p>
                </div>
            <?php
        }
    }
}

function wcct_init() {
    return WeDevs_WC_Conversion_Tracking::init();
}

// WeDevs_WC_Conversion_Tracking
wcct_init();

/**
 * Manage Capability
 *
 * @return void
 */
function wcct_manage_cap() {
    return apply_filters( 'wcct_capability', 'manage_options' );
}
