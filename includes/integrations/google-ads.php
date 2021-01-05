<?php
/**
 * Google Ads Integration
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng;

/**
 * What good is a Google Ads campaign if you can't track ROI?
 */
class GoogleAdsIntegration extends Integration {
	/**
	 * Set up our integration
	 */
	public function __construct() {
		$this->id     = 'google-ads';
		$this->name   = __( 'Google Ads', 'woocommerce-cnvrsn-trckng' );
		$this->events = array(
			// https://developers.google.com/gtagjs/reference/event
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
		$this->ga = IntegrationManager::active( 'google-analytics' );

		if ( $this->is_enabled() && $this->ga ) {
			// wipe the inline script analytics is going to enqueue in favor of our combined gtag script
			add_filter( 'cnvrsn_trckng_inline_scripts_google-analytics', '__return_empty_string' );
		}
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
			array( $this, 'settings_account_id_cb' ),
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
		$value      = $account_id ? $account_id : '';
		?>
		<label class="cnvrsn-custom-label">
			<?php echo esc_html__( 'Account ID', 'woocommerce-cnvrsn-trckng' ); ?>:
			<input type="text" name="<?php echo esc_attr( $this->settings_key( '[account_id]' ) ); ?>" placeholder="AW-123456789" value="<?php echo esc_attr( $value ); ?>"/>
		</label>
		<p class="help"><?php echo wp_kses( __( 'Provide your Google Ads Account ID. Usually it\'s something like <code>AW-123456789</code>.', 'woocommerce-cnvrsn-trckng' ), 'post' ); ?></p>
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
		echo '<p class="help">' . wp_kses( __( 'This is the hash Google creates and associates with your event. It\'s most findable in the conversion snippet <a href="https://support.google.com/google-ads/answer/7548399#Eventsnippet">generated for GTM</a>, like <code>\'send_to\': \'AW-123456789/<strong>ABcDefGHij8675309ETc</strong>\'</code>.', 'woocommerce-cnvrsn-trckng' ), 'post' ) . '</p>';
		foreach ( $this->get_events() as $event ) {
			$value = isset( $labels[ esc_attr( $event ) ] ) ? $labels[ esc_attr( $event ) ] : '';
			?>
			<label class="cnvrsn-custom-label cnvrsn-trckng-toggle-target" data-toggler="<?php echo esc_attr( $this->id . '-' . $event ); ?>">
				<?php echo esc_html( Events\get_event_label( $event ) ); ?>:
				<input type="text" name="<?php echo esc_attr( $this->settings_key( '[labels][' . $event . ']' ) ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
			</label>
			<?php
		}
	}

	/**
	 * For any added settings, we must have a sanitizer function or they won't be saved
	 *
	 * @param  array $cleaned An empty or previously-saved array of settings
	 * @param  array $saved Untrusted user-input we must sanitize before actually saving
	 * @since 0.1.0
	 */
	public function sanitize_settings_fields( $cleaned, $saved ) {
		if ( ! isset( $saved['integrations'][ $this->id ] ) ) {
			return $cleaned;
		}

		$integration = $saved['integrations'][ $this->id ];

		if ( isset( $integration['account_id'] ) ) {
			$cleaned['integrations'][ $this->id ]['account_id'] = esc_html( $integration['account_id'] );
		}

		if ( isset( $integration['labels'] ) && is_array( $integration['labels'] ) ) {
			foreach ( $integration['labels'] as $event => $label ) {
				@$cleaned['integrations'][ $this->id ]['labels'][ esc_attr( $event ) ] = esc_html( $label );
			}
		}

		return $cleaned;
	}

	/**
	 * Build the event object
	 *
	 * @param  string $event_name Google Ads event name
	 * @param  array  $params An array of parameters about the event
	 * @param  string $method Google Ads method to pass
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

		// Ads seems to be able to track based on being linked to Analytics,
		// but no one on the internet will say with certainty what's actually required
		// so to be safe, we'll switch to tag manager here and override
		// GA's tracking code in the instance that both are enabled

		// NOTE: we're counting on coming after analytics in our IntegrationManager

		wp_enqueue_script( 'cnvrsn-trckng-' . $this->id, 'https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $account_id ), array(), null, true );

		$script =
			'window.dataLayer = window.dataLayer || [];' .
			'function gtag(){dataLayer.push(arguments)};' .
			'gtag("js", new Date());' .
			'gtag("config", "' . esc_js( $account_id ) . '");';

		if ( $this->ga ) {
			// don't load analytics.js (because gtm is going to do it on its own anyway)
			wp_dequeue_script( 'cnvrsn-trckng-google-analytics' );
			// (inline script is emptied in integration_interactions() because it's too late to do it here)

			// finally, register the GA account
			$ua = $this->ga->get_plugin_settings( 'tracking_id' );
			if ( ! empty( $ua ) ) {
				$script .= 'gtag("config", "' . esc_js( $ua ) . '");';
			}
		}

		return $script;
	}

	/**
	 * Event: Purchase
	 *
	 * @param array $order_data An array of key => values about our order
	 * @since 0.1.0
	 */
	public function purchase( $order_data ) {
		$settings = $this->get_plugin_settings();
		if ( empty( $settings['account_id'] ) || empty( $settings['labels']['purchase'] ) ) { return; }

		$code = $this->build_event(
			'conversion',
			array(
				'send_to'        => $settings['account_id'] . '/' . $settings['labels']['purchase'],
				'transaction_id' => $order_data['order_number'],
				'value'          => $order_data['order_total'],
				'currency'       => $order_data['currency'],
			)
		);

		Events\add_to_footer( $code );
	}
}
