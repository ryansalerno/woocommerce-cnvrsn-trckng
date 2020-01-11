<?php

/**
 * Admin functions
 *
 * @package WooCommerce_Cnvrsn_Trckng
 */
namespace CnvrsnTrckng\Admin;
use CnvrsnTrckng\IntegrationManager;
use CnvrsnTrckng\Events;

/**
 * Init
 *
 * @since 0.1.0
 */
function setup() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\add_actions' );
}

/**
 * Register all our actions when appropriate
 *
 * @since 0.1.0
 */
function add_actions() {
	add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu', 20 );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\admin_enqueue_scripts' );
	add_action( 'admin_notices', __NAMESPACE__ . '\check_woocommerce_is_activated' );

	add_filter( 'plugin_action_links_' . plugin_basename( CNVRSN_FILE ), __NAMESPACE__ . '\plugin_action_links' );
}

/**
 * Register setting fields and sections
 *
 * @since  0.1.0
 */
function register_settings() {
	register_setting(
		'woocommerce-cnvrsn-trckng',
		'cnvrsn_trckng_settings',
		array(
			'type' => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_integration_settings'
		)
	);

	add_settings_section(
		'cnvrsn-integrations',
		'Integration Settings',
		'',
		'conversion-tracking'
	);

	make_integration_sections();
}

/**
 * Loop through our integrations and output their settings boxes
 *
 * @since  0.1.0
 */
function make_integration_sections() {
	foreach ( IntegrationManager::all_integrations() as $integration ) {
		$id = $integration->get_id();

		add_settings_field(
			'cnvrsn_trckng_settings[integrations][' . $id . '][enabled]',
			esc_html__( $integration->get_name(), 'woocommerce-cnvrsn-trckng' ),
			__NAMESPACE__ . '\integration_enabled_cb',
			'conversion-tracking',
			'cnvrsn-integrations',
			array(
				'integration' => $integration,
			)
		);

		do_action( 'cnvrsn_trckng_' . $id . '_render_settings_first' );

		add_settings_field(
			'cnvrsn_trckng_settings[integrations][' . $id . '][events]',
			'',
			__NAMESPACE__ . '\integration_events_cb',
			'conversion-tracking',
			'cnvrsn-integrations',
			array(
				'integration' => $integration,
			)
		);

		do_action( 'cnvrsn_trckng_' . $id . '_render_settings_last' );
	}
}

/**
 * This is a toggle, but it also is meant to appear as the header to a box that will open when it's enabled
 *
 * @since  0.1.0
 */
function integration_enabled_cb( $args ) {
	$id   = $args['integration']->get_id();
	$key  = 'cnvrsn_trckng_settings[integrations][' . $id . '][enabled]';
	$icon = dirname( __DIR__ ) . '/assets/images/'. $id .'.svg';
	?>
	<label class="integration-header">
		<?php if ( is_file( $icon ) ) { echo file_get_contents( $icon ); } ?>
		<h3><?php echo sanitize_text_field( $args['integration']->get_name() ); ?></h3>
		<input type="checkbox" class="toggler" name="<?php echo $key; ?>" data-toggle="<?php echo esc_attr( $id ); ?>" <?php checked( $args['integration']->is_enabled() ); ?>/>
		<span class="toggle"><span class="tooltip"></span></span>
	</label>
	<?php
}

/**
 * Output our event checkboxes
 *
 * @since  0.1.0
 */
function integration_events_cb( $args ) {
	$events = $args['integration']->get_events();
	if ( empty( $events ) ) { return; }

	$id  = $args['integration']->get_id();
	$key = 'cnvrsn_trckng_settings[integrations][' . $id . '][events]';

	$checkboxen = array();
	foreach ( (array) $events as $event ) {
		$checkboxen[] = '<label>' .
			'<input type="checkbox" name="' . $key . '[' . esc_attr( $event ) . ']' . '" ' . checked( $args['integration']->event_enabled( $event ), true, false ) . ' data-toggle="' . esc_attr( $id . '-' . $event ) . '"/>' .
			Events\get_event_label( $event ) .
		'</label>';
	}
	?>
	<h3><?php echo esc_html__( 'Supported Events:', 'woocommerce-cnvrsn-trckng' ); ?></h3>
	<ul class="event-checkboxes">
		<li><?php echo implode( '</li><li>', $checkboxen ); ?></li>
	</ul>
	<?php
}

/**
 * Get all replacement keys for an event and format them as help text
 *
 * @param  string $event
 * @return string
 * @since  0.1.0
 */
function get_replacement_help_text( $event ){
	$keys = Events\get_replacement_keys( $event );
	if ( empty( $keys ) ) { return; }

	$replacements = array_map(function($key){
		return '<code>{' . $key . '}</code>';
	}, $keys);

	return '<p class="help replacements">' . esc_html__( 'Dynamic replacement tags:', 'woocommerce-convrsn-trckng' ) . ' ' . implode( ', ', $replacements ) . '</p>';
}

/**
 * Sanitize settings for DB
 *
 * @param  array $settings Array of settings.
 * @since  0.1.0
 */
function sanitize_integration_settings( $settings ) {
	$new_settings = get_settings();

	if ( ! isset( $settings['integrations'] ) ) { return $new_settings; }

	foreach ( $settings['integrations'] as $id => $integration ) {
		@$new_settings['integrations'][$id]['enabled'] = ( isset( $integration['enabled'] ) && $integration['enabled'] );

		if ( isset( $integration['events'] ) && is_array( $integration['events'] ) ) {
			foreach ( $integration['events'] as $event => $enabled ) {
				@$new_settings['integrations'][$id]['events'][$event] = $enabled ? true : false;
			}
		}
	}

	return apply_filters( 'cnvrsn_trckng_sanitize_settings', $new_settings, $settings );
}

/**
 * Get plugin settings (with defaults)
 *
 * @return array
 * @since  0.1.0
 */
function get_settings() {
	$defaults = array(
		'integrations' => array(),
	);
	$settings = get_option( 'cnvrsn_trckng_settings', array() );

	return wp_parse_args( $settings, apply_filters( 'cnvrsn_trckng_default_settings', $defaults ) );
}

/**
 * wp_parse_args(), but multidimensional
 * https://mekshq.com/recursive-wp-parse-args-wordpress-function/
 *
 * @return array
 * @since  0.1.0
 */
// NOTE: we may someday want to have some more complicated default settings above....
/*
function meks_wp_parse_args( &$a, $b ) {
	$a = (array) $a;
	$b = (array) $b;
	$result = $b;
	foreach ( $a as $k => &$v ) {
		if ( is_array( $v ) && isset( $result[ $k ] ) ) {
			$result[ $k ] = meks_wp_parse_args( $v, $result[ $k ] );
		} else {
			$result[ $k ] = $v;
		}
	}
	return $result;
}
*/

/**
 * Output setting menu option
 *
 * @since  0.1.0
 */
function admin_menu() {
	add_submenu_page(
		'woocommerce',
		esc_html__( 'Conversion Tracking', 'woocommerce-cnvrsn-trckng' ),
		esc_html__( 'Conversion Tracking', 'woocommerce-cnvrsn-trckng' ),
		'manage_options',
		'conversion-tracking',
		__NAMESPACE__ . '\settings_screen'
	);
}

/**
 * Output setting screen
 *
 * @since 0.1.0
 */
function settings_screen() {
	?>
	<div class="wrap cnvrsn-trckng">
		<form action="options.php" method="post">

		<?php settings_fields( 'woocommerce-cnvrsn-trckng' ); ?>
		<?php do_settings_sections( 'conversion-tracking' ); ?>

		<?php submit_button(); ?>

		</form>
	</div>
	<?php
}

/**
 * Enqueue admin scripts/styles for settings
 *
 * @param  string $hook WP hook.
 * @since  0.1.0
 */
function admin_enqueue_scripts( $hook ) {
	wp_enqueue_style( 'cnvrsn-admin', plugins_url( '/assets/css/admin.css', __DIR__ ), array(), CNVRSN_VERSION );
	wp_enqueue_script( 'cnvrsn-admin', plugins_url( '/assets/js/admin.js', __DIR__ ), array(), CNVRSN_VERSION, true );
}

/**
 * Show a helpful Settings link in the plugin row
 *
 * @param  array $links
 * @return array
 * @since  0.1.0
 */
function plugin_action_links( $links ) {
	$links[] = '<a href="' . admin_url( 'admin.php?page=conversion-tracking' ) . '">' . __( 'Settings', 'woocommerce-cnvrsn-trckng' ) . '</a>';

	return $links;
}

/**
 * Let everyone know we aren't going to be useful without WooCommerce
 *
 * @since  0.1.0
 */
function check_woocommerce_is_activated() {
	if ( ! class_exists( 'woocommerce' ) ) {
		notice('<strong>Woocommerce Cnvrsn Trckng</strong> requires Woocommerce.', 'error');
		force_deactivate();
	}
}

/**
 * Turn ourselves off
 *
 * @return void
 * @since  0.1.0
 */
function force_deactivate(){
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	deactivate_plugins( plugin_basename( CNVRSN_FILE ) );
	if ( isset( $_GET['activate'] ) ){ unset( $_GET['activate'] ); }
	return;
}

/**
 * Display an admin notice
 *
 * @param  string $text The notice we want displayed
 * @param  string $type The type of notice (e.g. error, warning, updated, info)
 * @param  boolean $dismissable Whether the notice is dismissable
 * @return void
 * @since  0.1.0
 */
function notice( $text, $type = 'info', $dismissable = true ) {
	$classes = 'notice ' . $type;
	if ($dismissable){
		$classes .= ' is-dismissible';
	}
	?>
	<div class="<?php echo esc_attr( $classes ); ?>">
		<p><?php echo wp_kses( __( $text, 'woocommerce-cnvrsn-trckng' ), 'post' );?></p>
	</div>
	<?php
}
