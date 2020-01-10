<?php

/**
 * Google Ads Integration
 */
namespace CnvrsnTrckng;

class GoogleAdsIntegration extends Integration {
	/**
	 * Set up our integration
	 */
	function __construct() {
		$this->id       = 'google-ads';
		$this->name     = __( 'Google Ads', 'woocommerce-cnvrsn-trckng' );
		$this->events = array(
			'purchase',
		);
	}

	/**
	 * Get settings fields definitions
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public function get_settings_fields() {
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

		return apply_filters( 'cnvrsn_trckng_settings_' . $this->id, $settings );
	}

	/**
	 * Build the event object
	 *
	 * @param  string $event_name
	 * @param  array $params
	 * @param  string $method
	 * @return string
	 * @since 0.1.0
	 */
	public function build_event( $event_name, $params = array(), $method = 'event' ) {
		return sprintf( "gtag('%s', '%s', %s);", $method, $event_name, json_encode( $params, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Enqueue script
	 *
	 * @return string
	 * @since 0.1.0
	 */
	public function enqueue_script() {
		$settings   = $this->get_plugin_settings();
		$account_id = ! empty( $settings[$this->id]['account_id'] ) ? $settings[$this->id]['account_id'] : '';

		if ( empty( $account_id ) ) { return; }

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
	 * Event: Purchase
	 *
	 * @since 0.1.0
	 */
	public function purchase( $order_data ) {
		$settings   = $this->get_plugin_settings();
		$account_id = isset( $settings[$this->id]['account_id'] ) ? $settings[$this->id]['account_id'] : '';
		$label      = isset( $settings[$this->id]['events']['Purchase-label'] ) ? $settings[$this->id]['events']['Purchase-label'] : '';

		if ( empty( $account_id ) || empty( $label ) ) { return; }

		$code = $this->build_event( 'conversion', array(
			'send_to'        => sprintf( "%s/%s", $account_id, $label ),
			'transaction_id' => $order_data['order_number'],
			'value'          => $order_data['order_total'],
			'currency'       => $order_data['currency']
		) );

		wc_enqueue_js( $code );
	}
}
