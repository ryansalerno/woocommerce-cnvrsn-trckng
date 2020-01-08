<?php

/**
 * The admin page handler class
 */
class WCCT_Admin {

    /**
     * Constructor for WCCT_Admin class
     */
    function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu_page' ) );
    }

    /**
     * Enqueue Script
     *
     * @return void
     */
    public function enqueue_scripts() {

        /**
         * All style goes here
         */
        wp_enqueue_style( 'style', plugins_url( 'assets/css/style.css', WCCT_FILE ), false, filemtime( WCCT_PATH . '/assets/css/style.css' ) );

        /**
         * All script goes here
         */
        wp_enqueue_script( 'wcct-admin', plugins_url( 'assets/js/admin.js', WCCT_FILE ), array( 'jquery', 'wp-util' ), filemtime( WCCT_PATH . '/assets/js/admin.js' ), true );
    }

    /**
     * Add menu page
     *
     * @return void
     */
    public function admin_menu_page() {
        $menu_page      = apply_filters( 'wcct_menu_page', 'woocommerce' );
        $capability     = wcct_manage_cap();

        add_submenu_page( $menu_page, __( 'Conversion Tracking', 'woocommerce-conversion-tracking' ), __( 'Conversion Tracking', 'woocommerce-conversion-tracking' ), $capability, 'conversion-tracking', array( $this, 'conversion_tracking_template' ) );
    }

    /**
     * Conversion Tracking View Page
     *
     * @return void
     */
    public function conversion_tracking_template() {
        $integrations = wcct_init()->manager->get_integrations();

        include dirname( __FILE__ ) . '/views/admin.php';
    }
}
