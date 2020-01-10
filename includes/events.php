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
function suppported_events() {
	$default_events = array(
		'product_view',
		'category_view',
		'add_to_cart',
		'start_checkout',
		'purchase',
		'registration',
		'search',
		'wishlist',
	);

	return apply_filters( 'cnvrsn_trckng_supported_events', $default_events );
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
	dispatch_event( 'purchase', $order_id );
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
	dispatch_event( 'registration' );
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
		if ( $integration->supports( $event ) && method_exists( $integration, $event ) ) {
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
