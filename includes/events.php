<?php
/**
 * Manage and dispatch events
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng\Events;

use CnvrsnTrckng\Admin;
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
		'product_view'   => __( 'Product View', 'woocommerce-cnvrsn-trckng' ),
		'category_view'  => __( 'Category View', 'woocommerce-cnvrsn-trckng' ),
		'add_to_cart'    => __( 'Add to Cart', 'woocommerce-cnvrsn-trckng' ),
		'start_checkout' => __( 'Start Checkout', 'woocommerce-cnvrsn-trckng' ),
		'purchase'       => __( 'Successful Purchase', 'woocommerce-cnvrsn-trckng' ),
		'registration'   => __( 'User Registration', 'woocommerce-cnvrsn-trckng' ),
		'search'         => __( 'Search', 'woocommerce-cnvrsn-trckng' ),
		'wishlist'       => __( 'Add to Wishlist', 'woocommerce-cnvrsn-trckng' ),
	);

	return apply_filters( 'cnvrsn_trckng_supported_events', $default_events );
}

/**
 * Get (unique) enabled events
 *
 * @return array
 * @since 0.1.0
 */
function active_events() {
	$settings = Admin\get_settings();

	$enabled = array();

	if ( ! isset( $settings['integrations'] ) ) { return $enabled; }

	foreach ( $settings['integrations'] as $integration ) {
		if ( ! isset( $integration['events'] ) ) { continue; }

		foreach ( $integration['events'] as $event => $bool ) {
			if ( $bool ) { $enabled[ $event ] = 1; }
		}
	}

	return $enabled;
}

/**
 * Return an event's text label
 *
 * @param  string $event An event type slug
 * @return string
 * @since 0.1.0
 */
function get_event_label( $event ) {
	$events = supported_events();

	return isset( $events[ $event ] ) ? $events[ $event ] : $event;
}

/**
 * Register all potential actions
 *
 * @since 0.1.0
 */
function add_actions() {
	if ( empty( IntegrationManager::active_integrations() ) ) { return; }

	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

	$active_events = active_events();

	// view
	if ( isset( $active_events['product_view'] ) ) {
		add_action( 'woocommerce_after_single_product', __NAMESPACE__ . '\product_view' );
	}
	if ( isset( $active_events['category_view'] ) ) {
		add_action( 'woocommerce_after_shop_loop', __NAMESPACE__ . '\category_view' );
	}

	// cart and checkout
	if ( isset( $active_events['add_to_cart'] ) ) {
		add_action( 'woocommerce_add_to_cart', __NAMESPACE__ . '\add_to_cart', 9999, 6 );
	}
	if ( isset( $active_events['start_checkout'] ) ) {
		add_action( 'woocommerce_after_checkout_form', __NAMESPACE__ . '\start_checkout' );
	}

	// purchase
	if ( isset( $active_events['purchase'] ) ) {
		add_action( 'woocommerce_thankyou', __NAMESPACE__ . '\purchase' );
	}

	// registration
	if ( isset( $active_events['registration'] ) ) {
		add_action( 'woocommerce_registration_redirect', __NAMESPACE__ . '\wc_redirect_url' );
		add_action( 'template_redirect', __NAMESPACE__ . '\track_registration' );
	}

	// search
	if ( isset( $active_events['search'] ) ) {
		add_action( 'pre_get_posts', __NAMESPACE__ . '\search' );
	}

	// wishlist
	if ( isset( $active_events['wishlist'] ) ) {
		add_filter( 'yith_wcwl_added_to_wishlist', __NAMESPACE__ . '\wishlist' );
		add_action( 'woocommerce_wishlist_add_item', __NAMESPACE__ . '\wishlist' );
	}
}

/**
 * Do add to cart event
 *
 * @param  string $cart_item_key Hashed cart item key
 * @param  int    $product_id WP ID of the product
 * @param  int    $quantity The quantity added
 * @param  int    $variation_id WP ID of the variation, or 0
 * @param  object $variation Variation data, when applicablt
 * @param  object $cart_item_data Abtract of cart data
 * @since 0.1.0
 */
function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	$product_data = get_product_data( $product_id, $variation_id );
	$cart_data    = array(
		'qty' => $quantity,
	);
	dispatch_event( 'add_to_cart', array_merge( $product_data, $cart_data ) );
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
 * @param int $order_id WP order ID
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
	dispatch_event( 'product_view', get_product_data( get_the_ID() ) );
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
 * @param string $redirect URL to redirect to
 * @return string
 * @since 0.1.0
 */
function wc_redirect_url( $redirect ) {
	$redirect = add_query_arg(
		array(
			'_wc_user_reg' => 'true',
		),
		$redirect
	);

	return $redirect;
}

/**
 * Verify our added registration query arg
 *
 * @uses add_action()
 * @since 0.1.0
 */
function track_registration() {
	if ( isset( $_GET['_wc_user_reg'] ) && 'true' === $_GET['_wc_user_reg'] ) {
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
 * @param object $query WP_Query object
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
 * @param string $event An event type slug
 * @param mixed  $value Optional data to pass along
 * @since 0.1.0
 */
function dispatch_event( $event, $value = '' ) {
	foreach ( IntegrationManager::active_integrations() as $integration ) {
		if ( ! $integration->event_enabled( $event ) ) { continue; }

		if ( method_exists( $integration, $event ) ) {
			$integration->$event( $value );
			continue;
		}

		if ( method_exists( $integration, 'generic_event' ) ) {
			$integration->generic_event( $event, $value );
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
	if ( ! $active ) { return; }

	$scripts = array();

	foreach ( $active as $integration ) {
		$output = $integration->enqueue_script();
	}
}

/**
 * Fetch a bunch of order-related data for inclusion into the script
 *
 * @param  string $order_id WP order ID
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

	$backcompat = version_compare( WC_VERSION, '3.0', '<' );

	$data = array();

	$currency       = $backcompat ? $order->get_order_currency() : $order->get_currency();
	$order_number   = $order->get_order_number();
	$order_total    = $order->get_total() ? $order->get_total() : 0;
	$order_subtotal = $order->get_subtotal();
	$order_discount = $order->get_total_discount();
	$order_shipping = $order->get_total_shipping();
	$payment_method = $backcompat ? $order->payment_method : $order->get_payment_method();
	$used_coupons   = $order->get_coupon_codes() ? implode( ',', $order->get_coupon_codes() ) : '';

	$customer = $order->get_user();
	if ( $customer ) {
		$customer_id         = $customer->ID;
		$customer_email      = $customer->user_email;
		$customer_first_name = $customer->first_name;
		$customer_last_name  = $customer->last_name;
	}

	$replacement_keys = get_replacement_keys( 'order' );
	foreach ( $replacement_keys as $key ) {
		if ( isset( $$key ) ) { $data[ $key ] = $$key; }
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

	$customer_id         = $current_user->ID;
	$customer_email      = $current_user->user_email;
	$customer_first_name = esc_html( $current_user->user_firstname );
	$customer_last_name  = esc_html( $current_user->user_lastname );
	// $customer_username = esc_html( $current_user->user_login );
	// $customer_display_name = esc_html( $current_user->display_name );

	$replacement_keys = get_replacement_keys( 'customer' );
	foreach ( $replacement_keys as $key ) {
		if ( isset( $$key ) ) { $data[ $key ] = $$key; }
	}

	return $data;
}

/**
 * Fetch a bunch of product-related data for inclusion into the script
 *
 * @param  object $pid WP product ID
 * @param  string $vid Optional WP variation ID to use as an override
 * @return array
 * @since 0.1.0
 */
function get_product_data( $pid, $vid = '' ) {
	$data = array();

	$product = wc_get_product( $vid ? $vid : $pid );
	if ( ! is_a( $product, 'WC_Product' ) ) { return $data; }

	$backcompat = version_compare( WC_VERSION, '3.0', '<' );

	$product_id    = $product->get_sku() ? $product->get_sku() : $product->get_id();
	$product_name  = $product->get_name();
	$product_price = $product->get_price();

	$_cats          = array();
	$variation_data = $backcompat ? $product->variation_data : ( $product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $product->get_id() ) : '' );

	if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
		$product_variation = wc_get_formatted_variation( $variation_data, true );

		$parent_product = wc_get_product( $backcompat ? $product->parent->id : $product->get_parent_id() );
		$categories     = get_the_terms( $parent_product->get_id(), 'product_cat' );
	} else {
		$categories = get_the_terms( $product->get_id(), 'product_cat' );
	}

	if ( $categories ) {
		foreach ( $categories as $category ) {
			$_cats[] = $category->name;
		}
	}

	$product_category = implode( '/', $_cats );

	$replacement_keys = get_replacement_keys( 'product' );
	foreach ( $replacement_keys as $key ) {
		if ( isset( $$key ) ) { $data[ $key ] = $$key; }
	}

	return $data;
}

/**
 * Return valid keys for per-event replacement data
 *
 * @param  string $event An event type slug, or a genericized version of same
 * @return array
 * @since 0.1.0
 */
function get_replacement_keys( $event ) {
	$keys = array();

	// we primarily want to include event names for use in \Admin\get_replacement_help_text()
	// but will also sometimes include shorthand for a shared get_foo_data() call above
	switch ( $event ) {
		case 'add_to_cart':
		case 'product':
			$keys = array( 'product_id', 'product_name', 'product_price', 'product_category', 'product_variation' );
			break;
		case 'product_view':
			$keys = array( 'product_id', 'product_name', 'product_price', 'product_category' );
			break;
		case 'purchase':
		case 'order':
			$keys = array( 'currency', 'order_number', 'order_total', 'order_subtotal', 'order_discount', 'order_shipping', 'payment_method', 'used_coupons', 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name' );
			break;
		case 'registration':
		case 'customer':
			$keys = array( 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name' );
			break;
	}

	return $keys;
}
