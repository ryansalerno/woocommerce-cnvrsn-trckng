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
		'product_view'     => __( 'Product View', 'woocommerce-cnvrsn-trckng' ),
		'category_view'    => __( 'Category View', 'woocommerce-cnvrsn-trckng' ),
		'add_to_cart'      => __( 'Add to Cart', 'woocommerce-cnvrsn-trckng' ),
		'remove_from_cart' => __( 'Remove from Cart', 'woocommerce-cnvrsn-trckng' ),
		'start_checkout'   => __( 'Start Checkout', 'woocommerce-cnvrsn-trckng' ),
		'purchase'         => __( 'Successful Purchase', 'woocommerce-cnvrsn-trckng' ),
		'registration'     => __( 'User Registration', 'woocommerce-cnvrsn-trckng' ),
		'search'           => __( 'Search', 'woocommerce-cnvrsn-trckng' ),
		'wishlist'         => __( 'Add to Wishlist', 'woocommerce-cnvrsn-trckng' ),
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

	return apply_filters( 'cnvrsn_trckng_active_events', $enabled );
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

	// cart
	if ( isset( $active_events['add_to_cart'] ) ) {
		// this event doesn't correspond to a page of its own, so it's more complicated....
		// instead of hooking into several actions to capture all the permutations of cart
		// events WC makes available, or resorting to on-page button click-hijacking or ajax-listening
		// we can tap into the serverside processing of the event and then (optionally)
		// defer the action until the next page load for JS snippets and other client-side integrations

		// this means you could lose the event if there isn't a subsequent pageload
		// but if someone adds to cart and then bails, that feels like an acceptable loss
		add_action( 'woocommerce_add_to_cart', __NAMESPACE__ . '\add_to_cart', 9999, 6 );

		// TODO: re-add on restore?
		// TODO: should increasing quantity count as an add to cart event?
	}
	if ( isset( $active_events['remove_from_cart'] ) ) {
		add_action( 'woocommerce_cart_item_removed', __NAMESPACE__ . '\remove_from_cart', 10, 2 );

		// TODO: should decreasing quantity count as a remove from cart event?
	}

	// checkout
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

	add_action( 'wp_footer', __NAMESPACE__ . '\deferred_event' );
}

/**
 * Do add to cart event
 *
 * @param  string $cart_item_key Hashed cart item key
 * @param  int    $product_id WP ID of the product
 * @param  int    $quantity The quantity added
 * @param  int    $variation_id WP ID of the variation, or 0
 * @param  array  $variation Variation data, when applicable
 * @param  array  $cart_item_data Optional extra cart data
 * @since 0.3.0
 */
function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	$product_data = get_product_data( $product_id, $variation_id );
	$cart_data    = array(
		'currency' => get_woocommerce_currency(),
		'qty'      => $quantity,
	);

	// we ought to set a price, but is it better to prefer the product's natural price,
	// or the price as reflected in the cart (somehow)?
	// intuition tells me the cart price is more important, but WC doesn't give us that directly
	// we could calculate it here, but it's possible we could introduce some edge case errors...
	// but maybe that's better than not providing a price at all?

	$cart_item = WC()->cart->get_cart_item( $cart_item_key );
	if ( ! empty( $cart_item['line_total'] ) ) {
		$cart_data['cart_price'] = round( $cart_item['line_total'] / $cart_item['quantity'], 2 );
	}

	dispatch_event( 'add_to_cart', array_merge( $product_data, $cart_data ) );
}

/**
 * Do remove from cart event
 *
 * @param  string  $cart_item_key Hashed cart item key
 * @param  WC_Cart $cart Full WC Cart class
 * @since 0.3.0
 */
function remove_from_cart( $cart_item_key, $cart ) {
	$cart_item = $cart->removed_cart_contents[$cart_item_key];
	if ( empty( $cart_item['product_id'] ) ) { return; }

	$variation_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : '';
	$product_data = get_product_data( $cart_item['product_id'], $variation_id );
	$cart_data    = array(
		'currency' => get_woocommerce_currency(),
		'qty'      => ! empty( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1,
	);

	if ( ! empty( $cart_item['line_total'] ) ) {
		$cart_data['cart_price'] = round( $cart_item['line_total'] / $cart_item['quantity'], 2 );
	}

	dispatch_event( 'remove_from_cart', array_merge( $product_data, $cart_data ) );
}

/**
 * Do start checkout event
 *
 * @since 0.1.1
 */
function start_checkout() {
	dispatch_event( 'start_checkout', get_cart_data() );
}

/**
 * Do completed checkout event
 *
 * @param int $order_id WP order ID
 * @since 0.1.0
 * @since 0.6.0 Force atomicity of event dispatches
 */
function purchase( $order_id ) {
	$data = get_purchase_data( $order_id );

	// NOTE: I can't think of a reason why you'd want anything happening here to happen more than once,
	//       but we're not offering any granularity about integrations or events and maybe we ought to?
	if ( did_dispatch( 'purchase', $order_id, $data ) ) { return; }

	dispatch_event( 'purchase', $data );

	force_atomicity( 'purchase', $order_id, $data );
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
 * @since 0.2.1
 */
function category_view() {
	dispatch_event( 'category_view', get_category_data() );
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
 * Dig up a deferred event and fire it
 *
 * @since 0.3.0
 */
function deferred_event() {
	// WC_Session isn't available at plugins_loaded so we can't conditionally check this before adding the action that gets us here
	if ( ! WC()->session->__isset( 'cnvrsn_trckng_deferred_event' ) ) { return; }

	$deferred = WC()->session->get( 'cnvrsn_trckng_deferred_event' );

	// we delete our key immediately, so even if validation fails the session key goes away instead of checking and failing endlessly
	WC()->session->__unset( 'cnvrsn_trckng_deferred_event' );

	if ( empty( $deferred['event'] ) || empty( $deferred['data'] ) ) { return; }

	dispatch_event( $deferred['event'] . '_deferred', $deferred['data'] );
}

/**
 * Try to dispatch an event if an active integration supports it
 *
 * @param string $event    An event type slug
 * @param mixed  $value    Optional data to pass along
 * @param string $callback Specific function to call (for custom events)
 * @since 0.1.0
 */
function dispatch_event( $event, $value = '', $callback = false ) {
	do_action( 'cnvrsn_trckng_dispatch_event_' . $event, $value );

	foreach ( IntegrationManager::active_integrations() as $integration ) {
		if ( ! $integration->event_enabled( $event ) ) { continue; }

		// TODO: I think the ability to null the data is enough, but if we need to get into the weeds, I'm leaving this here
		// if ( apply_filters( 'cnvrsn_trckng_event_override_' . $integration->get_id() . '_' . $event, false, $value, $integration ) ) { continue; }

		if ( $callback && is_callable( $callback ) ) {
			$callback( $value, $integration );
			continue;
		}

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

	$inline_js = '';

	foreach ( $active as $integration ) {
		$output = $integration->enqueue_script();

		if ( ! empty( $output ) ) {
			$inline_js .= apply_filters( 'cnvrsn_trckng_inline_scripts_' . $integration->get_id(), $output );
		}
	}

	if ( ! empty( $inline_js ) ) {
		add_to_footer( $inline_js );
	}
}

/**
 * Render some code in the footer
 * (because wc_enqueue_js() requires jQuery and doesn't support tracking pixels or non-js content)
 *
 * @param string $code   Some user-entered code that needs to end up on the page
 * @param string $format Either 'script' or 'kses', which controls the output formatting
 * @since 0.1.0
 */
function add_to_footer( $code, $format = 'script' ) {
	add_action(
		'wp_footer',
		function() use( $code, $format ) {
			switch ( $format ) {
				case 'script':
					echo '<script type="text/javascript">' . sanitize_js( $code ) . '</script>';
					break;

				case 'kses':
					echo wp_kses( $code, 'post' );
					break;

				default:
					echo $code;
					break;
			}
		},
		500
	);
}

/**
 * Before printing JS anywhere, maybe check it at least a little bit
 *
 * @param  string $js Some arbitrary javascript
 * @return string $js Possibly safer javascript
 * @since 0.1.0
 */
function sanitize_js( $js ) {
	// wc_enqueue_js() does these things
	$js = wp_check_invalid_utf8( $js );
	$js = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $js );
	$js = str_replace( "\r", '', $js );

	return $js;
}

/**
 * Fetch a bunch of order-related data for inclusion into the script
 *
 * @param  int $order_id WP order ID
 * @return array
 * @since 0.1.0
 */
function get_purchase_data( $order_id ) {
	$order = wc_get_order( $order_id );

	// bail if not a valid order
	if ( ! is_a( $order, 'WC_Order' ) ) { return array(); }

	// TODO: break this up and only fetch what's requested
	// this overkill feels better than repeating the fetching code everywhere,
	// but is less efficient than getting the minimal amount of data

	$backcompat = version_compare( WC_VERSION, '3.0', '<' );

	$currency       = $backcompat ? $order->get_order_currency() : $order->get_currency();
	$order_number   = $order->get_order_number();
	$order_total    = $order->get_total() ? $order->get_total() : 0;
	$order_subtotal = $order->get_subtotal();
	$order_discount = $order->get_total_discount();
	$order_shipping = $order->get_total_shipping();
	$order_tax      = $order->get_total_tax();
	$payment_method = $backcompat ? $order->payment_method : $order->get_payment_method();
	$used_coupons   = $order->get_coupon_codes() ? implode( ',', $order->get_coupon_codes() ) : '';

	$customer_id         = $order->get_user_id();
	$customer_email      = $order->get_billing_email();
	$customer_first_name = $order->get_billing_first_name();
	$customer_last_name  = $order->get_billing_last_name();

	$data = @compact( get_replacement_keys( 'order' ) );

	$data['_order_id'] = $order_id;
	$data['_item_ids'] = array();
	foreach ( (array) $order->get_items() as $item ) {
		$data['_item_ids'][] = $item->get_product_id();
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
	$current_user = wp_get_current_user();
	if ( ! ( $current_user instanceof WP_User ) ) { return array(); }

	$customer_id         = $current_user->ID;
	$customer_email      = $current_user->user_email;
	$customer_first_name = esc_html( $current_user->user_firstname );
	$customer_last_name  = esc_html( $current_user->user_lastname );
	// $customer_username = esc_html( $current_user->user_login );
	// $customer_display_name = esc_html( $current_user->display_name );

	$data = @compact( get_replacement_keys( 'customer' ) );

	return $data;
}

/**
 * Fetch a bunch of product-related data for inclusion into the script
 *
 * @param  int    $pid WP product ID
 * @param  string $vid Optional WP variation ID to use as an override
 * @return array
 * @since 0.1.0
 */
function get_product_data( $pid, $vid = '' ) {
	$product = wc_get_product( $vid ? $vid : $pid );
	if ( ! is_a( $product, 'WC_Product' ) ) { return array(); }

	$product_id        = $product->get_sku() ? $product->get_sku() : $product->get_id();
	$product_name      = html_entity_decode( $product->get_name() );
	$product_price     = $product->get_price();
	$product_permalink = $product->get_permalink();
	$product_category  = get_product_category_line( $product );

	$data = @compact( get_replacement_keys( 'product' ) );

	return $data;
}

/**
 * Fetch a bunch of category-related data for inclusion into the script
 *
 * @param  int $cid WP category ID
 * @return array
 * @since 0.2.1
 */
function get_category_data() {
	global $wp_query;

	$category = $wp_query->get_queried_object();
	$products = $wp_query->get_posts();

	// this could be a real category, or could be the main shop page
	if ( ! empty( $category->term_id ) ) {
		$category_id        = $category->term_id;
		$category_name      = html_entity_decode( $category->name );
		$category_permalink = get_term_link( $category );
	} else {
		$category_id        = 0;
		$category_name      = html_entity_decode( apply_filters( 'cnvrsn_trckng_category_name_for_shop_page', $category->labels->all_items, $category ) );
		$category_permalink = get_post_type_archive_link( $category->name );
	}

	$data = @compact( get_replacement_keys( 'category' ) );

	$data['_item_ids'] = wp_list_pluck( $products, 'ID' );

	return $data;
}

/**
 * Fetch a bunch of cart-related data for inclusion into the script
 *
 * @return array
 * @since 0.1.1
 */
function get_cart_data() {
	$cart = WC()->cart;

	$currency      = get_woocommerce_currency();
	$cart_total    = $cart ? $cart->total : 0;
	$cart_subtotal = $cart->get_subtotal();
	$cart_discount = $cart->get_discount_total();
	$cart_shipping = $cart->get_shipping_total();
	$cart_tax      = $cart->get_total_tax();
	$coupons       = implode( ',', $cart->get_applied_coupons() );
	$cart_count    = $cart->get_cart_contents_count();

	$customer            = $cart->get_customer();
	$customer_id         = $customer->get_id();
	$customer_email      = $customer->get_email();
	$customer_first_name = $customer->get_first_name();
	$customer_last_name  = $customer->get_last_name();

	$data = @compact( get_replacement_keys( 'cart' ) );

	$data['cart_items'] = array();

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
		if ( ! $product || ! $product->exists() || ! $cart_item['quantity'] > 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) { continue; }

		$data['cart_items'][] = array(
			'id'        => $product->get_id(),
			'sku'       => $product->get_sku(),
			'name'      => html_entity_decode( $product->get_name() ),
			'permalink' => apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key ),
			'category'  => get_product_category_line( $product ),
			'price'     => $cart_item['line_total'],
			'quantity'  => $cart_item['quantity'],
		);
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
		case 'category': // shorthand
		case 'category_view':
			$keys = array( 'category_id', 'category_name', 'category_permalink' );
			break;

		case 'product': // shorthand
		case 'product_view':
			$keys = array( 'product_id', 'product_name', 'product_price', 'product_category', 'product_variation', 'product_permalink' );
			break;

		case 'add_to_cart':
		case 'remove_from_cart':
			$keys = array( 'product_id', 'product_name', 'product_price', 'product_category', 'product_variation', 'product_permalink', 'qty', 'currency' );
			break;

		case 'cart': // shorthand
		case 'start_checkout':
			$keys = array( 'currency', 'cart_total', 'cart_subtotal', 'cart_discount', 'cart_shipping', 'cart_tax', 'coupons', 'cart_count', 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name' );
			break;

		case 'order': // shorthand
		case 'purchase':
			$keys = array( 'currency', 'order_number', 'order_total', 'order_subtotal', 'order_discount', 'order_shipping', 'order_tax', 'payment_method', 'used_coupons', 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name' );
			break;

		case 'customer': // shorthand
		case 'registration':
			$keys = array( 'customer_id', 'customer_email', 'customer_first_name', 'customer_last_name' );
			break;
	}

	return $keys;
}

/**
 * Grab product categories and try to make sense of them
 *
 * @param  WC_Product $product A WC_Product instance
 * @return string
 * @since 0.1.1
 */
function get_product_category_line( $product ) {
	$backcompat = version_compare( WC_VERSION, '3.0', '<' );

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
			$_cats[] = html_entity_decode( $category->name );
		}
	}

	return apply_filters( 'cnvrsn_trckng_product_category_line', implode( '/', $_cats ), $product, $_cats );
}

/**
 * Store a representation of an event to be able to prevent it from firing more than once
 *
 * @param  string $event An event type slug
 * @param  int    $id    WP object ID
 * @param  array  $data  Product data, for hashing and verifying uniqueness
 * @return void
 * @since 0.6.0
 */
function force_atomicity( $event, $id, $data ) {
	if ( ! get_post_type( $id ) ) { return; }

	$hash = hash_data( $data );

	// NOTE: we're not checking for collisions here, so make sure you've returned as appropriate before calling this

	update_post_meta( $id, '_cnvrsn_trckng_event_' . esc_attr( $event ), $hash );
}

/**
 * Check a stored event representation to determine if we've been here before
 *
 * @param  string  $event        An event type slug
 * @param  int     $id           WP object ID
 * @param  array   $data         Product data, for hashing and verifying uniqueness
 * @return boolean $did_dispatch Whether this event (with this data) has already fired
 * @since 0.6.0
 */
function did_dispatch( $event, $id, $data ) {
	$stored = get_post_meta( $id, '_cnvrsn_trckng_event_' . esc_attr( $event ), true );
	if ( ! $stored ) { return false; }

	$hash = hash_data( $data );

	// NOTE: make sure event data doesn't include anything dynamic since we're specifically comparing hashes and not just the existence of a key

	return apply_filters( 'cnvrsn_trckng_skip_repeated_' . esc_attr( $event ), $stored === $hash, $id, $data );
}

/**
 * Store a representation of an event to be able to prevent it from firing more than once
 *
 * @param  array  $data Product data, for hashing and verifying uniqueness
 * @return string $hash
 * @since 0.6.0
 */
function hash_data( $data ) {
	return hash( 'sha256', json_encode( $data ) );
}