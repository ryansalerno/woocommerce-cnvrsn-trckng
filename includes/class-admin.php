<?php

/**
 * The admin page handler class
 */
class Cnvrsn_Admin {

	/**
	 * Constructor for Convrsn_Admin class
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
		wp_enqueue_style( 'style', plugins_url( 'assets/css/style.css', CNVRSN_FILE ), false, filemtime( CNVRSN_PATH . '/assets/css/style.css' ) );

		/**
		 * All script goes here
		 */
		wp_enqueue_script( 'cnvrsn-admin', plugins_url( 'assets/js/admin.js', CNVRSN_FILE ), array( 'jquery', 'wp-util' ), filemtime( CNVRSN_PATH . '/assets/js/admin.js' ), true );
	}

	/**
	 * Add menu page
	 *
	 * @return void
	 */
	public function admin_menu_page() {
		$menu_page      = apply_filters( 'cnvrsn_menu_page', 'woocommerce' );
		$capability     = cnvrsn_manage_cap();

		add_submenu_page( $menu_page, __( 'Conversion Tracking', 'woocommerce-cnvrsn-trckng' ), __( 'Conversion Tracking', 'woocommerce-cnvrsn-trckng' ), $capability, 'conversion-tracking', array( $this, 'conversion_tracking_template' ) );
	}

	/**
	 * Conversion Tracking View Page
	 *
	 * @return void
	 */
	public function conversion_tracking_template() {
		$integrations = cnvrsn_init()->manager->get_integrations();

		include dirname( __FILE__ ) . '/views/admin.php';
	}
}
