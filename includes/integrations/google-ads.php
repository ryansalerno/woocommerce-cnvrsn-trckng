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

		parent::__construct();
	}

	/**
	 * Add any custom settings before the events list
	 *
	 * @since 0.1.0
	 */
	public function add_settings_fields_first() {
		add_settings_field(
			'integrations[' . $this->id . '][account_id]',
			'',
			array($this, 'settings_account_id_cb'),
			'conversion-tracking',
			'cnvrsn-integrations'
		);
	}

	/**
	 * Render Account ID setting
	 *
	 * @since 0.1.0
	 */
	public function settings_account_id_cb() {
		$account_id = $this->get_plugin_settings( 'account_id' );
		$value = $account_id ? $account_id : '';
		?>
		<label class="cnvrsn-custom-label">
			<?php echo esc_html__( 'Account ID', 'woocommerce-cnvrsn-trckng' ); ?>:
			<input type="text" name="<?php echo $this->settings_key( '[account_id]' ); ?>" placeholder="AW-123456789" value="<?php echo esc_attr( $value ); ?>"/>
		</label>
		<p class="help"><?php echo __( 'Provide your Google Ads Account ID. Usually it\'s something like <code>AW-123456789</code>.', 'woocommerce-cnvrsn-trckng' ); ?></p>
		<?php
	}

	/**
	 * Add any custom settings after the events list
	 *
	 * @since 0.1.0
	 */
	public function add_settings_fields_last() {
		add_settings_field(
			'integrations[' . $this->id . '][labels]',
			'',
			array( $this, 'settings_labels_cb' ),
			'conversion-tracking',
			'cnvrsn-integrations'
		);
	}

	/**
	 * We need a Label for each event
	 *
	 * @since 0.1.0
	 */
	public function settings_labels_cb() {
		$labels = $this->get_plugin_settings( 'labels' );

		echo '<h3>' . esc_html__( 'Event Labels:', 'woocommerce-cnvrsn-trckng' ) . '</h3>';
		foreach ( $this->get_events() as $event ) {
			$value = isset( $labels[esc_attr($event)] ) ? $labels[esc_attr($event)] : '';
			?>
			<label class="cnvrsn-custom-label cnvrsn-trckng-toggle-target" data-toggler="<?php echo esc_attr( $this->id . '-' . $event ); ?>">
				<?php echo Events\get_event_label( $event ); ?>:
				<input type="text" name="<?php echo $this->settings_key( '[labels][' . $event . ']' ); ?>" value="<?php echo esc_attr($value); ?>"/>
			</label>
			<?php
		}
	}

	/**
	 * For any added settings, we must have a sanitizer function or they won't be saved
	 *
	 * @since 0.1.0
	 */
	public function sanitize_settings_fields( $cleaned, $saved ) {
		if ( ! isset( $saved['integrations'][$this->id] ) ) {
			return $cleaned;
		}

		$integration = $saved['integrations'][$this->id];

		if ( isset( $integration['account_id'] ) ) {
			$cleaned['integrations'][$this->id]['account_id'] = esc_html( $integration['account_id'] );
		}

		if ( isset( $integration['labels'] ) && is_array( $integration['labels'] ) ) {
			foreach ( $integration['labels'] as $event => $label ) {
				@$cleaned['integrations'][$this->id]['labels'][esc_attr($event)] = esc_html( $label );
			}
		}

		return $cleaned;
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
		$account_id = $this->get_plugin_settings( 'account_id' );
		if ( ! $account_id ) { return; }

		$script = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $account_id ) . '"></script>';
		$script .= '<script>'.
			'window.dataLayer = window.dataLayer || [];'.
			'function gtag(){dataLayer.push(arguments)};'.
			'gtag("js", new Date());'.
			'gtag("config", "' . esc_js( $account_id ) . '");'.
		'</script>';

		return $script;
	}

	/**
	 * Event: Purchase
	 *
	 * @since 0.1.0
	 */
	public function purchase( $order_data ) {
		$settings = $this->get_plugin_settings();
		if ( ! isset( $settings['account_id'] ) || empty( $settings['account_id'] ) || ! isset( $settings['labels'] ) || ! isset( $settings['labels']['purchase'] ) || empty( $settings['labels']['purchase'] ) ) { return; }

		$code = $this->build_event( 'conversion', array(
			'send_to'        => $settings['account_id'] . '/' . $settings['labels']['purchase'],
			'transaction_id' => $order_data['order_number'],
			'value'          => $order_data['order_total'],
			'currency'       => $order_data['currency']
		) );

		wc_enqueue_js( $code );
	}
}
