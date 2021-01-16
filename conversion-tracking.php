<?php
/**
 * Plugin Name:     WooCommerce Cnvrsn Trckng
 * Plugin URI:      https://github.com/ryansalerno/woocommerce-cnvrsn-trckng
 * Description:     Forked from the excellent WooCommerce Conversion Tracking plugin by Tareq Hasan (with lots of bits removed)
 * Author:          Ryan (after Tareq Hasan)
 * Text Domain:     woocommerce-cnvrsn-trckng
 * Version:         0.5.5
 * License:         GPL3

 * WC requires at least: 2.3
 * WC tested up to: 4.9.0

 * @package WooCommerce_Cnvrsn_Trckng
 */

// Original code Copyright (c) 2017 Tareq Hasan (email: tareq@wedevs.com). All rights reserved.
// (License moved to its own file)

// bail if accessed directly
defined( 'ABSPATH' ) || exit;

define( 'CNVRSN_VERSION', '0.5.5' );
define( 'CNVRSN_FILE', __FILE__ );

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/integrations.php';

\CnvrsnTrckng\Admin\setup();
\CnvrsnTrckng\Events\setup();
