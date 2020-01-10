<?php

/**
 * Google Ads Integration
 */
class Cnvrsn_Integration_Google_Ads extends Cnvrsn_Integration {

	/**
	 * Constructor for Cnvrsn_Integration_Google
	 */
	function __construct() {
		$this->id       = 'google-ads';
		$this->name     = __( 'Google Ads', 'woocommerce-cnvrsn-trckng' );
		$this->enabled  = false;
		$this->supports = array(
			'checkout',
		);
	}

	/**
	 * Get settings
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = array(
			'id'  => array(
				'type'        => 'text',
				'name'        => 'account_id',
				'label'       => __( 'Account ID', 'woocommerce-cnvrsn-trckng' ),
				'value'       => '',
				'placeholder' => 'AW-123456789',
				'help'        => __( 'Provide the Google Ads Account ID. Usually it\'s something like <code>AW-123456789</code>.', 'woocommerce-cnvrsn-trckng' )
			),
			'events'    => array(
				'type'    => 'multicheck',
				'name'    => 'events',
				'label'   => __( 'Events', 'woocommerce-cnvrsn-trckng' ),
				'value'   => '',
				'options' => array(
					'Purchase'  => array(
						'event_label_box'   => true,
						'label'             => __( 'Purchase', 'woocommerce-cnvrsn-trckng' ),
						'label_name'       => 'Purchase-label',
						'placeholder'      => 'Add Your Purchase Label'
					),
				)
			),
		);

		return apply_filters( 'cnvrsn_settings_adwords', $settings );
	}

	/**
	 * Build the event object
	 *
	 * @param  string $event_name
	 * @param  array $params
	 * @param  string $method
	 *
	 * @return string
	 */
	public function build_event( $event_name, $params = array(), $method = 'event' ) {
		return sprintf( "gtag('%s', '%s', %s);", $method, $event_name, json_encode( $params, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Enqueue script
	 *
	 * @return void
	 */
	public function enqueue_script() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings   = $this->get_integration_settings();
		$account_id = ! empty( $settings[0]['account_id'] ) ? $settings[0]['account_id'] : '';

		if ( empty( $account_id ) ) {
			return;
		}

		$script = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $account_id ) . '"></script>';
		$script .= '<script>'.
			'window.dataLayer = window.dataLayer || [];'.
			'function gtag(){dataLayer.push(arguments)};'.
			'gtag("js", new Date());'.
			'gtag("config", "' . esc_attr( $account_id ) . '");'.
		'</script>';

		return $script;
	}

	/**
	 * Check Out google adwords
	 *
	 * @return void
	 */
	public function checkout( $order_id ) {
		$settings   = $this->get_integration_settings();
		$account_id = isset( $settings[0]['account_id'] ) ? $settings[0]['account_id'] : '';
		$label      = isset( $settings[0]['events']['Purchase-label'] ) ? $settings[0]['events']['Purchase-label'] : '';

		if ( empty( $account_id ) || empty( $label ) ) {
			return;
		}

		$order = new WC_Order( $order_id );

		$code = $this->build_event( 'conversion', array(
			'send_to'        => sprintf( "%s/%s", $account_id, $label ),
			'transaction_id' => $order_id,
			'value'          => $order->get_total() ? $order->get_total() : 0,
			'currency'       => get_woocommerce_currency()
		) );

		wc_enqueue_js( $code );
	}
}
