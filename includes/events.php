<?php

/**
 * Manage and dispatch events
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */
namespace CnvrsnTrckng\Events;
use CnvrsnTrckng\IntegrationManager;

/**
 * Init
 *
 * @since 0.1.0
 */
function setup() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\add_actions' );
}

/**
 * Return a list of supported events
 *
 * @return array
 * @since 0.1.0
 */
function supported_events() {
	$default_events = array(
		'product_view' => esc_html__( 'Product View', 'woocommerce-cnvrsn-trckng' ),
		'category_view' => esc_html__( 'Category View', 'woocommerce-cnvrsn-trckng' ),
		'add_to_cart' => esc_html__( 'Add to Cart', 'woocommerce-cnvrsn-trckng' ),
		'start_checkout' => esc_html__( 'Start Checkout', 'woocommerce-cnvrsn-trckng' ),
		'purchase' => esc_html__( 'Successful Purchase', 'woocommerce-cnvrsn-trckng' ),
		'registration' => esc_html__( 'User Registration', 'woocommerce-cnvrsn-trckng' ),
		'search' => esc_html__( 'Search', 'woocommerce-cnvrsn-trckng' ),
		'wishlist' => esc_html__( 'Add to Wishlist', 'woocommerce-cnvrsn-trckng' ),
	);

	return apply_filters( 'cnvrsn_trckng_supported_events', $default_events );
}

/**
 * Return an event's text label
 *
 * @param  string $event
 * @return string
 * @since 0.1.0
 */
function get_event_label( $event ) {
	$events = supported_events();

	return isset( $events[$event] ) ? $events[$event] : $event;
}

/**
 * Register all potential actions
 *
 * @since 0.1.0
 */
function add_actions() {
	if ( empty( IntegrationManager::active_integrations() ) ) { return; }

	add_action( 'wp_footer', __NAMESPACE__ . '\enqueue_scripts' );

	// TODO: conditionally add actions here only when we know they're going to fire

	// view
	add_action( 'woocommerce_after_single_product', __NAMESPACE__ . '\product_view' );
	add_action( 'woocommerce_after_shop_loop', __NAMESPACE__ . '\category_view' );

	// cart and checkout
	add_action( 'woocommerce_add_to_cart', __NAMESPACE__ . '\add_to_cart', 9999, 4 );
	add_action( 'woocommerce_after_checkout_form', __NAMESPACE__ . '\start_checkout' );

	// purchase
	add_action( 'woocommerce_thankyou', __NAMESPACE__ . '\purchase' );

	// registration
	add_action( 'woocommerce_registration_redirect', __NAMESPACE__ . '\wc_redirect_url' );
	add_action( 'template_redirect', __NAMESPACE__ . '\track_registration' );

	// search
	add_action( 'pre_get_posts', __NAMESPACE__ . '\search' );

	// wishlist
	add_filter( 'yith_wcwl_added_to_wishlist', __NAMESPACE__ . '\wishlist' );
	add_action( 'woocommerce_wishlist_add_item', __NAMESPACE__ . '\wishlist' );
}

/**
 * Do add to cart event
 *
 * @since 0.1.0
 */
function add_to_cart() {
	dispatch_event( 'add_to_cart' );
}

/**
 * Do start checkout event
 *
 * @since 0.1.0
 */
function start_checkout() {
	dispatch_event( 'start_checkout' );
}

/**
 * Do completed checkout event
 *
 * @param  int $order_id
 * @since 0.1.0
 */
function purchase( $order_id ) {
	dispatch_event( 'purchase', get_purchase_data( $order_id ) );
}

/**
 * Do product view event
 *
 * @since 0.1.0
 */
function product_view() {
	dispatch_event( 'product_view' );
}

/**
 * Do category view event
 *
 * @since 0.1.0
 */
function category_view() {
	dispatch_event( 'category_view' );
}

/**
 * Adds a url query arg to determine newly registered user
 *
 * @uses woocommerce_registration_redirect action
 * @param string $redirect
 * @return string
 * @since 0.1.0
 */
function wc_redirect_url( $redirect ) {
	$redirect = add_query_arg( array(
		'_wc_user_reg' => 'true'
	), $redirect );

	return $redirect;
}

/**
 * Verify our added registration query arg
 *
 * @uses add_action()
 * @since 0.1.0
 */
function track_registration() {
	if ( isset( $_GET['_wc_user_reg'] ) && $_GET['_wc_user_reg'] == 'true' ) {
		add_action( 'wp_footer', __NAMESPACE__ . '\complete_registration' );
	}
}

/**
 * Do customer registration event
 *
 * @since 0.1.0
 */
function complete_registration() {
	dispatch_event( 'registration', get_user_data() );
}

/**
 * Do search event
 *
 * @since 0.1.0
 */
function search( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		dispatch_event( 'search' );
	}
}

/**
 * Do wishlist event
 *
 * @since 0.1.0
 */
function wishlist() {
	dispatch_event( 'wishlist' );
}

/**
 * Try to dispatch an event if an active integration supports it
 *
 * @param  string $event
 * @param  mixed $value
 * @since 0.1.0
 */
function dispatch_event( $event, $value = '' ) {
	foreach ( IntegrationManager::active_integrations() as $integration ) {
		if ( ! $integration->event_enabled( $event ) ) { continue; }
			$integration->$event( $value );
		}
	}
}

/**
 * Run the integration's enqueue_script function and get it on the page
 *
 * @since 0.1.0
 */
function enqueue_scripts() {
	$active = IntegrationManager::active_integrations();
	if ( ! $active ){ return; }

	$scripts = array();

	foreach ( $active as $integration ) {
		$scripts[ $integration->get_id() ] = $integration->enqueue_script();
	}

	if ( empty( $scripts ) ) { return; }

	?>
	<!-- Begin: WooCommerce Cnvrsn Trckng -->
	<?php echo implode( PHP_EOL, apply_filters( 'cnvrsn_trckng_enqueued_scripts', $scripts ) ); ?>
	<!-- End: WooCommerce Cnvrsn Trckng -->
	<?php
}

/**
 * Fetch a bunch of order-related data for inclusion into the script
 *
 * @param  string $order_id
 * @return array
 * @since 0.1.0
 */
function get_purchase_data( $order_id ) {
	$order = wc_get_order( $order_id );

	// bail if not a valid order
	if ( ! is_a( $order, 'WC_Order' ) ) { return; }

	// TODO: break this up and only fetch what's requested
	// this overkill feels better than repeating the fetching code everywhere,
	// but is less efficient than getting the minimal amount of data

	$backcompat = version_compare( WC()->version, '3.0', '<' );

	$data = array();

	$currency       = $backcompat ? $order->get_order_currency() : $order->get_currency();
	$order_number   = $order->get_order_number();
	$order_total    = $order->get_total() ? $order->get_total() : 0;
	$order_subtotal = $order->get_subtotal();
	$order_discount = $order->get_total_discount();
	$order_shipping = $order->get_total_shipping();
	$payment_method = $backcompat ? $order->payment_method : $order->get_payment_method();
	$used_coupons   = $order->get_used_coupons() ? implode( ',', $order->get_used_coupons() ) : '';

	$customer = $order->get_user();
	if ( $customer ) {
		$customer_id         =  $customer->ID;
		$customer_email      =  $customer->user_email;
		$customer_first_name =  $customer->first_name;
		$customer_last_name  =  $customer->last_name;
	}

	$replacement_keys = get_replacement_keys( 'purchase' );
	foreach ( $replacement_keys as $key ) {
		if ( isset( $$key ) ) { $data[$key] = $$key; }
	}

	return $data;
}

/**
 * Fetch a bunch of user-related data for inclusion into the script
 *
 * @return array
 * @since 0.1.0
 */
function get_user_data() {
	$data = array();

	$current_user = wp_get_current_user();
	if ( ! ( $current_user instanceof WP_User ) ) { return $data; }

	$customer_id = $current_user->ID;
	$customer_email = $current_user->user_email;
	$customer_first_name = esc_html( $current_user->user_firstname );
	$customer_last_name = esc_html( $current_user->user_lastname );
	// $customer_username = esc_html( $current_user->user_login );
	// $customer_display_name = esc_html( $current_user->display_name );

	$replacement_keys = get_replacement_keys( 'registration' );
	foreach ( $replacement_keys as $key ) {
		if ( isset( $$key ) ) { $data[$key] = $$key; }
	}

	return $data;
}

/**
 * Return valid keys for per-event replacement data
 *
 * @param  string $event
 * @return array
 * @since 0.1.0
 */
function get_replacement_keys( $event ) {
	$keys = array();

	switch ($event) {
		case 'purchase':
			$keys = array('currency', 'order_number', 'order_total', 'order_subtotal', 'order_discount', 'order_shipping', 'payment_method', 'used_coupons', 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name');
			break;
		case 'registration':
			$keys = array('customer_id', 'customer_email', 'customer_first_name', 'customer_last_name');
			break;
	}

	return $keys;
}
