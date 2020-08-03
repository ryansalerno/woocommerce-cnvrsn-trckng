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
			// https://developers.google.com/gtagjs/reference/event
			'start_checkout',
			'purchase',
		);
		$this->asyncs = array( 'cnvrsn-trckng-' . $this->id );
		$this->defers = array( 'cnvrsn-trckng-' . $this->id );

		parent::__construct();
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
		return sprintf( "gtag('%s', '%s', %s);", $method, $event_name, json_encode( $params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
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
				'name'     => ! empty( $item['name'] ) ? html_entity_decode( $item['name'] ) : '',
				// 'brand' =>  '',
				'category' => ! empty( $item['category'] ) ? html_entity_decode( $item['category'] ) : '',
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
	 * @param  int $item_id WC order id
	 * @return array
	 * @since 0.1.1
	 */
	protected function item( $item ) {
		$product = Events\get_product_data( $item->get_product_id() );
		if ( empty( $product['product_id'] ) ) { return; }

		$item = array(
			'id'       => isset( $product['product_id'] ) ? $product['product_id'] : '',
			'name'     => isset( $product['product_name'] ) ? html_entity_decode( $product['product_name'] ) : '',
			// 'brand' =>  '',
			'category' => isset( $product['product_category'] ) ? html_entity_decode( $product['product_category'] ) : '',
			'quantity' => $item->get_quantity(),
			'price'    => $item->get_total(),
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

		wp_enqueue_script( 'cnvrsn-trckng-' . $this->id, 'https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $tracking_id ), array(), CNVRSN_VERSION, true );
		$script =
			'window.dataLayer = window.dataLayer || [];' .
			'function gtag(){dataLayer.push(arguments)};' .
			'gtag("js", new Date());' .
			'gtag("config", "' . esc_js( $tracking_id ) . '");';

		return $script;
	}

	/**
	 * Event: Start Checkout
	 *
	 * @param array $order_data An array of key => values about our order
	 * @since 0.1.1
	 */
	public function start_checkout( $cart_data ) {
		$tracking_id = $this->get_plugin_settings( 'tracking_id' );
		if ( ! $tracking_id ) { return; }

		// value, currency, items, coupon
		$params = array(
			'value'          => $cart_data['cart_total'],
			'currency'       => $cart_data['currency'],
			'items'          => $this->items_from_cart_data( $cart_data['cart_items'] ),
			'coupon'         => $cart_data['coupons'],
		);

		$code = $this->build_event( 'begin_checkout', $params );

		Events\add_to_footer( $code );
	}

	/**
	 * Event: Purchase
	 *
	 * @param array $order_data An array of key => values about our order
	 * @since 0.1.1
	 */
	public function purchase( $order_data ) {
		$tracking_id = $this->get_plugin_settings( 'tracking_id' );
		if ( ! $tracking_id ) { return; }

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

		$code = $this->build_event( 'purchase', $params );

		Events\add_to_footer( $code );
	}
}
