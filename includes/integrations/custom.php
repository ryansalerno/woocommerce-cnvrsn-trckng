<?php

/**
 * Custom Integrations
 */
namespace CnvrsnTrckng;

class CustomIntegration extends Integration {
	/**
	 * Set up our integration
	 */
	function __construct() {
		$this->id       = 'custom';
		$this->name     = __( 'Custom', 'woocommerce-cnvrsn-trckng' );
		$this->events = array(
			'purchase',
			'registration'
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
			array(
				'type'  => 'textarea',
				'name'  => 'purchase',
				'label' => __( 'Successful Order', 'woocommerce-cnvrsn-trckng' ),
				'value' => '',
				'help'  => sprintf( /* translators: %s: dynamic values */
								   __( 'Put your JavaScript tracking scripts here. You can use dynamic values: %s', 'woocommerce-cnvrsn-trckng' ),
					'<code>{customer_id}</code>, <code>{customer_email}</code>, <code>{customer_first_name}</code>, <code>{customer_last_name}</code>, <code>{order_number}</code>, <code>{order_total}</code>, <code>{order_subtotal}</code>, <code>{order_discount}</code>, <code>{order_shipping}</code>, <code>{currency}</code>, <code>{payment_method}</code>'
				),
			),
			array(
				'type'  => 'textarea',
				'name'  => 'registration',
				'label' => __( 'Registration Scripts', 'woocommerce-cnvrsn-trckng' ),
				'value' => '',
			)
		);

		return apply_filters( 'cnvrsn_trckng_settings_' . $this->id, $settings );
	}

	/**
	 * Enqueue script
	 *
	 * @since 0.1.0
	 */
	public function enqueue_script() {}

	/**
	 * Event: Purchase
	 *
	 * @since 0.1.0
	 */
	public function purchase( $order_data ) {
		$code = $this->get_plugin_settings();

		if ( isset( $code['purchase'] ) && ! empty( $code['purchase'] ) ) {
			echo wp_kses( $this->dynamic_data_replacement( $code['purchase'], $order_data ), 'post' );
		}
	}

	/**
	 * Event: Registration
	 *
	 * @since 0.1.0
	 */
	public function registration() {
		$code = $this->get_plugin_settings();

		if ( isset( $code['registration'] ) && ! empty( $code['registration'] ) ) {
			echo wp_kses( $code['registration'], 'post' );
		}
	}
}
