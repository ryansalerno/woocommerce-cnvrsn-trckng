<?php
/**
 * Custom Integrations
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */

namespace CnvrsnTrckng;

/**
 * Handy-dandy textareas for injecting any custom codes we can handle
 */
class CustomIntegration extends Integration {
	/**
	 * Set up our integration
	 */
	public function __construct() {
		$this->id     = 'custom';
		$this->name   = __( 'Custom', 'woocommerce-cnvrsn-trckng' );
		$this->events = array(
			'purchase',
			'product_view',
			'registration',
		);

		parent::__construct();
	}

	/**
	 * Add any custom settings after the events list
	 *
	 * @since 0.1.0
	 */
	public function add_settings_fields_last() {
		add_settings_field(
			'integrations[' . $this->id . '][codes]',
			'',
			array( $this, 'settings_codes_cb' ),
			'conversion-tracking',
			'cnvrsn-integrations'
		);
	}

	/**
	 * We need a textarea for each event in order to insert custom tracking codes/pixels
	 *
	 * @since 0.1.0
	 */
	public function settings_codes_cb() {
		$codes = $this->get_plugin_settings( 'codes' );

		echo '<h3>' . esc_html__( 'Enter your codes and pixels:', 'woocommerce-cnvrsn-trckng' ) . '</h3>';
		foreach ( $this->get_events() as $event ) {
			$value = '';
			if ( $codes && isset( $codes[ esc_attr( $event ) ] ) ) {
				$value = $codes[ esc_attr( $event ) ];
			}
			?>
			<fieldset class="cnvrsn-custom-code cnvrsn-trckng-toggle-target" data-toggler="<?php echo esc_attr( $this->id . '-' . $event ); ?>">
				<label class="cnvrsn-custom-label" for="cnvrsn-code-<?php echo esc_attr( $event ); ?>"><?php echo esc_html( Events\get_event_label( $event ) ); ?>:</label>
				<textarea id="cnvrsn-code-<?php echo esc_attr( $event ); ?>" name="<?php echo esc_attr( $this->settings_key( '[codes][' . $event . ']' ) ); ?>" rows="5"><?php echo esc_html( $value ); ?></textarea>
				<?php echo wp_kses( Admin\get_replacement_help_text( $event ), 'post' ); ?>
			</fieldset>
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

		if ( isset( $integration['codes'] ) && is_array( $integration['codes'] ) ) {
			foreach ( $integration['codes'] as $event => $code ) {
				@$cleaned['integrations'][ $this->id ]['codes'][ esc_attr( $event ) ] = wp_kses( $code, 'post' );
			}
		}

		return $cleaned;
	}

	/**
	 * Generic event handler
	 *
	 * @param string $event An event type slug
	 * @param array  $data An optional array of data from which to make replacements
	 * @since 0.1.0
	 */
	public function generic_event( $event, $data = array() ) {
		$codes = $this->get_plugin_settings( 'codes' );

		if ( empty( $codes[ $event ] ) ) { return; }

		echo wp_kses( $this->dynamic_data_replacement( $codes[ $event ], $data ), 'post' );
	}
}
