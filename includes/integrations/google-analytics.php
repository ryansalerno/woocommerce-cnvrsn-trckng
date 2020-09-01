<?php
/**
 * Google Analytics Integration
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng;

/**
 * Enhanced Ecommerce shouldn't be so hard
 */
class GoogleAnalyticsIntegration extends Integration {
	/**
	 * Set up our integration
	 */
	public function __construct() {
		$this->id     = 'google-analytics';
		$this->name   = __( 'Google Analytics', 'woocommerce-cnvrsn-trckng' );
		$this->events = array(
			// https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce
			'category_view',
			'product_view',
			'add_to_cart',
			'remove_from_cart',
			'start_checkout',
			'purchase',
		);
		$this->asyncs = array( 'cnvrsn-trckng-' . $this->id );
		$this->defers = array( 'cnvrsn-trckng-' . $this->id );

		parent::__construct();
	}

	/**
	 * Do something after all other integrations are loaded
	 *
	 * @return void
	 * @since 0.2.0
	 */
	public function integration_interactions() {
		$this->gtag = apply_filters( 'cnvrsn_trckng_' . $this->id . '_use_gtag', ! empty( IntegrationManager::active( 'google-ads' ) ) );
	}

	/**
	 * Add any custom settings before the events list
	 *
	 * @since 0.1.1
	 */
	public function add_settings_fields_first() {
		add_settings_field(
			'integrations[' . $this->id . '][tracking_id]',
			'',
			array( $this, 'settings_tracking_id_cb' ),
			'conversion-tracking',
			'cnvrsn-integrations'
		);
	}

	/**
	 * Render Tracking ID setting
	 *
	 * @since 0.1.1
	 */
	public function settings_tracking_id_cb() {
		$tracking_id = $this->get_plugin_settings( 'tracking_id' );
		$value       = $tracking_id ? $tracking_id : '';
		?>
		<label class="cnvrsn-custom-label">
			<?php echo esc_html__( 'Tracking ID', 'woocommerce-cnvrsn-trckng' ); ?>:
			<input type="text" name="<?php echo esc_attr( $this->settings_key( '[tracking_id]' ) ); ?>" placeholder="UA-123456789-1" value="<?php echo esc_attr( $value ); ?>"/>
		</label>
		<p class="help"><?php echo wp_kses( __( 'Provide your Google Analytics Tracking ID. Usually it\'s something like <code>UA-123456789-1</code>.', 'woocommerce-cnvrsn-trckng' ), 'post' ); ?></p>
		<?php
	}

	/**
	 * For any added settings, we must have a sanitizer function or they won't be saved
	 *
	 * @param  array $cleaned An empty or previously-saved array of settings
	 * @param  array $saved Untrusted user-input we must sanitize before actually saving
	 * @since 0.1.1
	 */
	public function sanitize_settings_fields( $cleaned, $saved ) {
		if ( ! isset( $saved['integrations'][ $this->id ] ) ) {
			return $cleaned;
		}

		$integration = $saved['integrations'][ $this->id ];

		if ( isset( $integration['tracking_id'] ) ) {
			$cleaned['integrations'][ $this->id ]['tracking_id'] = esc_html( $integration['tracking_id'] );
		}

		return $cleaned;
	}

	/**
	 * Build the event object
	 *
	 * @param  string $event_name Google Analytics event name
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method Google Analytics method to pass
	 * @return string
	 * @since 0.1.1
	 */
	public function build_event( $event_name, $params = array(), $method = 'event' ) {
		$tracker    = $this->is_gtag() ? 'gtag' : 'ga';
		$easy_gtags = array(
			'purchase'         => 1,
			'begin_checkout'   => 1,
			'view_item'        => 1,
			'view_item_list'   => 1,
			'add_to_cart'      => 1,
			'remove_from_cart' => 1,
		);

		if ( $tracker === 'gtag' && isset( $easy_gtags[$event_name] ) ) {
			// gtag has a simple call structure, so we can re-use this function most of the time
			return $this->build_event_gtag( $event_name,  $params, $method );
		} else {
			// ga:ec actions are more complicated and require some hoop jumping
			// (this also allows us to have gtag-specific routines if necessary)
			$callable = 'build_event_' . $tracker . '_' . $event_name;

			return method_exists( $this, $callable ) ? $this->$callable( $params, $method ) : '';
		}
	}

	/**
	 * Re-usable gtag events
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce
	 *
	 * @param  string $event_name Google Analytics event name
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method Google Analytics method to pass
	 * @return string
	 * @since 0.2.0
	 */
	public function build_event_gtag( $event_name, $params = array(), $method = 'event' ) {
		return sprintf( 'gtag("%s", "%s", %s);', $method, $event_name, json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Enhanced ecommerce product listing view
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#product-impression
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.2.1
	 */
	public function build_event_ga_view_item_list( $params = array(), $method = '' ) {
		$code = ! empty( $params['items'] ) ? $this->ga_items( $params['items'], true ) : '';

		return $code;
	}

	/**
	 * Enhanced ecommerce product detail view
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#product-detail-view
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.2.1
	 */
	public function build_event_ga_view_item( $params = array(), $method = '' ) {
		$code = ! empty( $params['items'] ) ? $this->ga_items( $params['items'] ) : '';
		if ( empty( $code) ) { return ''; }

		$code .= 'ga("ec:setAction", "detail");';

		return $code;
	}

	/**
	 * Enhanced ecommerce add to cart
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#add-remove-cart
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.3.0
	 */
	public function build_event_ga_add_to_cart( $params = array(), $method = '' ) {
		$code = ! empty( $params['items'] ) ? $this->ga_items( $params['items'] ) : '';
		if ( empty( $code) ) { return ''; }

		$code .= 'ga("ec:setAction", "add");';

		return $code;
	}

	/**
	 * Enhanced ecommerce remove from cart
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#add-remove-cart
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.3.0
	 */
	public function build_event_ga_remove_from_cart( $params = array(), $method = '' ) {
		$code = ! empty( $params['items'] ) ? $this->ga_items( $params['items'] ) : '';
		if ( empty( $code) ) { return ''; }

		$code .= 'ga("ec:setAction", "remove");';

		return $code;
	}

	/**
	 * Enhanced ecommerce checkout progress
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#measuring-transactions
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.2.0
	 */
	public function build_event_ga_begin_checkout( $params = array(), $method = '' ) {
		$code = ! empty( $params['items'] ) ? $this->ga_items( $params['items'] ) : '';
		if ( empty( $code) ) { return ''; }

		$params = $this->ga_param_translate( $params );
		$params['step'] = 1;

		$code .= sprintf( 'ga("ec:setAction", "checkout", %s);', json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		return $code;
	}

	/**
	 * Enhanced ecommerce purchase event
	 *
	 * @see: https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#measuring-transactions
	 *
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method [unused]
	 * @return string
	 * @since 0.2.0
	 */
	public function build_event_ga_purchase( $params = array(), $method = '' ) {
		$code   = ! empty( $params['items'] ) ? $this->ga_items( $params['items'] ) : '';
		$params = $this->ga_param_translate( $params );

		$code .= sprintf( 'ga("ec:setAction", "purchase", %s);', json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		return $code;
	}

	/**
	 * Translate from gtag keys to ga keys
	 *
	 * @param  array $params key => value params
	 * @return array
	 * @since 0.2.0
	 */
	protected function ga_param_translate( $params ) {
		$translated = array();

		$replace = array(
			'transaction_id' => 'id',
			'value'          => 'revenue',
		);
		$unset   = array(
			'items'    => 1,
			'currency' => 1,
		);

		foreach ( (array) $params as $key => $value ) {
			if ( isset( $unset[$key] ) || empty( $value ) ) { continue; }

			$_key = isset( $replace[$key] ) ? $replace[$key] : $key;
			$translated[$_key] = $value;
		}

		return $translated;
	}

	/**
	 * Translate from gtag keys to ga keys
	 *
	 * @param  array $item key => value params
	 * @return array
	 * @since 0.2.1
	 */
	protected function ga_item_translate( $item ) {
		$translated = array();

		$replace = array(
			'list_name'     => 'list',
			'list_position' => 'position',
		);

		foreach ( $item as $key => $value ) {
			$_key = isset( $replace[$key] ) ? $replace[$key] : $key;
			$translated[$_key] = $value;
		}

		return $translated;
	}

	/**
	 * Output as many ec:addProduct calls as needed
	 * (after a certain number of products, it would be more compact to have a for loop in the JS, but....)
	 *
	 * @param  array $items as returned by our items_by_orderid() function
	 * @param  bool  $is_impression (since 0.2.1)
	 * @return array
	 * @since 0.2.0
	 */
	protected function ga_items( $items, $is_impression = false ) {
		$ec  = '';
		$add = $is_impression ? 'addImpression' : 'addProduct';

		foreach ( (array) $items as $item ) {
			$ec .= 'ga("ec:' . $add . '", ' . json_encode( $this->ga_item_translate( $item ), JSON_UNESCAPED_SLASHES ) . ');' . PHP_EOL;
		}

		return $ec;
	}

	/**
	 * Format the array of items returned from cart data
	 *
	 * @param  array $cart_items Items key as returned from Events\get_cart_data()
	 * @return array
	 * @since 0.1.1
	 */
	protected function items_from_cart_data( $cart_items ) {
		$items = array();

		foreach ( (array) $cart_items as $item ) {
			$items[] = array(
				'id'       => ! empty( $item['sku'] ) ? $item['sku'] : $item['id'],
				'name'     => ! empty( $item['name'] ) ? $item['name'] : '',
				// 'brand' =>  '',
				'category' => ! empty( $item['category'] ) ? $item['category'] : '',
				'quantity' => $item['quantity'],
				'price'    => $item['price'],
			);
		}

		return array_filter( $items );
	}

	/**
	 * Get an array of items from an order id
	 *
	 * @param  int $order_id WC order id
	 * @return array
	 * @since 0.1.1
	 */
	protected function items_by_orderid( $order_id ) {
		$items = array();
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return array(); }

		foreach ( (array) $order->get_items() as $item_key => $item ) {
			$items[] = $this->item( $item );
		}

		return array_filter( $items );
	}

	/**
	 * Get product data, formatted as an array of params gtag expects
	 *
	 * Note: since this could be a WC_Product or WC_Order_Item_Product,
	 *       some methods need checking before calling
	 *
	 * @param  WC_Product $item WC product object
	 * @return array
	 * @since 0.1.1
	 */
	protected function item( $item ) {
		$pid = method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : $item->get_id();
		$product = Events\get_product_data( $pid );
		if ( empty( $product['product_id'] ) ) { return; }

		$item = array(
			'id'       => isset( $product['product_id'] ) ? $product['product_id'] : '',
			'name'     => isset( $product['product_name'] ) ? $product['product_name'] : '',
			// 'brand' =>  '',
			'category' => isset( $product['product_category'] ) ? $product['product_category'] : '',
			'quantity' => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : '',
			'price'    => method_exists( $item, 'get_total' ) ? $item->get_total() : '',
		);

		return array_filter( $item );
	}

	/**
	 * Enqueue script
	 *
	 * @return string
	 * @since 0.1.1
	 */
	public function enqueue_script() {
		$tracking_id = $this->get_plugin_settings( 'tracking_id' );
		if ( ! $tracking_id ) { return; }

		// despite Google's docs, the older analytics.js is better than using gtag for the case of only running GA
		// see: https://github.com/googleanalytics/autotrack/issues/202#issuecomment-333744194
		// and: https://github.com/GoogleChrome/lighthouse/issues/10783

		if ( $this->gtag ) {
			$external_url = 'https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $tracking_id );

			$script =
				'window.dataLayer=window.dataLayer||[];' .
				'function gtag(){dataLayer.push(arguments)};' .
				'gtag("js", new Date());' .
				'gtag("config", "' . esc_js( $tracking_id ) . '");';
		} else {
			$external_url = 'https://www.google-analytics.com/analytics.js';

			$script =
				'window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};' .
				'ga("create", "' . esc_js( $tracking_id ) . '", "auto");' .
				'ga("set", "transport", "beacon");' . // https://github.com/philipwalton/blog/blob/master/articles/the-google-analytics-setup-i-use-on-every-site-i-build.md#loading-analyticsjs
				'ga("require", "ec");' .
				'ga("send", "pageview");';
		}

		wp_enqueue_script( 'cnvrsn-trckng-' . $this->id, $external_url, array(), null, true );

		return $script;
	}

	/**
	 * Event: Category View
	 *
	 * @param array $category_data An array of key => values about our category
	 * @since 0.2.1
	 */
	public function category_view( $category_data ) {
		// items -> id, name, list_name, brand, category, variant, list_position, price
		$params = array(
			'items' => array(),
		);

		$list_name = sprintf( '%s %s', _x( 'Category:', $this->id, 'woocommerce-cnvrsn-trckng' ), $category_data['category_name'] );

		for ( $i = 0; $i < count( $category_data['_item_ids'] ); $i++ ) {
			$product = $this->item( wc_get_product( $category_data['_item_ids'][$i] ) );

			$product['list_name']     = $list_name;
			$product['list_position'] = $i + 1; // compensate for zero-based arrays

			$params['items'][] = $product;
		}

		$code = $this->build_event( 'view_item_list', $params );

		$this->add_code( $code );
	}

	/**
	 * Event: Product View
	 *
	 * @param array $product_data An array of key => values about our product
	 * @since 0.2.1
	 */
	public function product_view( $product_data ) {
		// items -> id, name, brand, category, variant, price
		$params = array(
			'items' => array( array(
				'id'       => isset( $product_data['product_id'] ) ? $product_data['product_id'] : '',
				'name'     => isset( $product_data['product_name'] ) ? $product_data['product_name'] : '',
				// 'brand' =>  '',
				'category' => isset( $product_data['product_category'] ) ? $product_data['product_category'] : '',
				// 'price' =>  isset( $product_data['product_price'] ) ? $product_data['product_price'] : '',
			) ),
		);

		$code = $this->build_event( 'view_item', $params );

		$this->add_code( $code );
	}

	/**
	 * Event: Add to Cart (defer until next pageload)
	 *
	 * @param array $add_data An array of key => values about our product
	 * @since 0.3.0
	 */
	public function add_to_cart( $add_data ) {
		$this->defer_event( $add_data );
	}

	/**
	 * Event: Add to Cart (actually dispatch)
	 *
	 * @param array $add_data An array of key => values about our product
	 * @since 0.3.0
	 */
	public function add_to_cart_deferred( $add_data ) {
		// value, currency, items
		$params = array(
			// 'value'    => $add_data['cart_total'], // ?
			'currency' => $add_data['currency'],
			'items'    => array( array(
				'id'       => isset( $add_data['product_id'] ) ? $add_data['product_id'] : '',
				'name'     => isset( $add_data['product_name'] ) ? $add_data['product_name'] : '',
				// 'brand' =>  '',
				'category' => isset( $add_data['product_category'] ) ? $add_data['product_category'] : '',
				'quantity' => isset( $add_data['qty'] ) ? $add_data['qty'] : 1,
				'price'    => isset( $add_data['cart_price'] ) ? $add_data['cart_price'] : $add_data['product_price'],
			) ),
		);

		$code = $this->build_event( 'add_to_cart', $params );

		$this->add_code( $code );
	}

	/**
	 * Event: Remove from Cart (defer until next pageload)
	 *
	 * @param array $remove_data An array of key => values about our product
	 * @since 0.3.0
	 */
	public function remove_from_cart( $remove_data ) {
		$this->defer_event( $remove_data );
	}

	/**
	 * Event: Remove from Cart (actually dispatch)
	 *
	 * @param array $remove_data An array of key => values about our product
	 * @since 0.3.0
	 */
	public function remove_from_cart_deferred( $remove_data ) {
		// value, currency, items
		$params = array(
			// 'value'    => $remove_data['cart_total'], // ?
			'currency' => $remove_data['currency'],
			'items'    => array( array(
				'id'       => isset( $remove_data['product_id'] ) ? $remove_data['product_id'] : '',
				'name'     => isset( $remove_data['product_name'] ) ? $remove_data['product_name'] : '',
				// 'brand' =>  '',
				'category' => isset( $remove_data['product_category'] ) ? $remove_data['product_category'] : '',
				'quantity' => isset( $remove_data['qty'] ) ? $remove_data['qty'] : 1,
				'price'    => isset( $remove_data['cart_price'] ) ? $remove_data['cart_price'] : $remove_data['product_price'],
			) ),
		);

		$code = $this->build_event( 'remove_from_cart', $params );

		$this->add_code( $code );
	}

	/**
	 * Event: Start Checkout
	 *
	 * @param array $cart_data An array of key => values about our cart
	 * @since 0.1.1
	 */
	public function start_checkout( $cart_data ) {
		// value, currency, items, coupon
		$params = array(
			'value'          => $cart_data['cart_total'],
			'currency'       => $cart_data['currency'],
			'items'          => $this->items_from_cart_data( $cart_data['cart_items'] ),
			'coupon'         => $cart_data['coupons'],
		);

		$code = $this->build_event( 'begin_checkout', $params );

		$this->add_code( $code );
	}

	/**
	 * Event: Purchase
	 *
	 * @param array $order_data An array of key => values about our order
	 * @since 0.1.1
	 */
	public function purchase( $order_data ) {
		// transaction_id, value, currency, tax, shipping, items, coupon
		$params = array(
			'transaction_id' => $order_data['order_number'],
			'value'          => $order_data['order_total'],
			'currency'       => $order_data['currency'],
			'tax'            => $order_data['order_tax'],
			'shipping'       => $order_data['order_shipping'],
			'items'          => $this->items_by_orderid( $order_data['_order_id'] ),
			'coupon'         => $order_data['used_coupons'],
		);

		// https://developers.google.com/analytics/devguides/collection/gtagjs/enhanced-ecommerce#measure_purchases
		$code = $this->build_event( 'purchase', $params );

		$this->add_code( $code );
	}

	/**
	 * Determine if tracking code is using gtag() or regular ga()
	 *
	 * @return bool
	 * @since 0.2.0
	 */
	public function is_gtag() {
		return $this->gtag;
	}

	/**
	 * Wrapper for Events\add_to_footer() with any integration-specific escape hatches necessary
	 *
	 * @param  string $code Some user-entered code that needs to end up on the page
	 * @return void
	 * @since 0.2.1
	 */
	public function add_code( $code ) {
		$tracking_id = $this->get_plugin_settings( 'tracking_id' );
		if ( ! $tracking_id ) { return; }

		Events\add_to_footer( $code );
	}
}
