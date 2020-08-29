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
			'purchase'       => 1,
			'begin_checkout' => 1,
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
	 * Output as many ec:addProduct calls as needed
	 * (after a certain number of products, it would be more compact to have a for loop in the JS, but....)
	 *
	 * @param  array $items as returned by our items_by_orderid() function
	 * @return array
	 * @since 0.2.0
	 */
	protected function ga_items( $items ) {
		$ec = '';

		foreach ( (array) $items as $item ) {
			$ec .= 'ga("ec:addProduct", ' . json_encode( $item, JSON_UNESCAPED_SLASHES ) . ');' . PHP_EOL;
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
	 * @param  WC_Product $item WC product object
	 * @return array
	 * @since 0.1.1
	 */
	protected function item( $item ) {
		$product = Events\get_product_data( $item->get_product_id() );
		if ( empty( $product['product_id'] ) ) { return; }

		$item = array(
			'id'       => isset( $product['product_id'] ) ? $product['product_id'] : '',
			'name'     => isset( $product['product_name'] ) ? $product['product_name'] : '',
			// 'brand' =>  '',
			'quantity' => $item->get_quantity(),
			'price'    => $item->get_total(),
			'category' => isset( $product['product_category'] ) ? $product['product_category'] : '',
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
