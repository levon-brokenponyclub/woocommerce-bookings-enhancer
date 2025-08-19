<?php
/**
 * Plugin Name: WooCommerce Bookings Enhancer
 * Description: Morning / Afternoon / Full Day slots with mirrored availability. If product 4856 or 4838 has a Full Day booking, product 573 is fully booked for that date (server + UI).
 * Version: 2.1.1
 * Author: Supersonic Playground
 * Author URI: https://www.supersonicplayground.com
 */

if (!defined('ABSPATH')) exit;

// --- Constants ---
define('SSPG_DEBUG', true);
define('SSPG_DESTINATION_ID', 573);
define('SSPG_SOURCE_IDS', json_encode([4856, 4838]));
define('SSPG_BUTTONS_USE_GROUP_AVAIL', true);

// --- Include modules ---
require_once __DIR__ . '/includes/light-logger.php';
require_once __DIR__ . '/includes/mirror-group.php';
require_once __DIR__ . '/includes/slot-inference.php';
require_once __DIR__ . '/includes/admin-panel.php';
require_once __DIR__ . '/includes/frontend-ui.php';
require_once __DIR__ . '/includes/frontend-script.php';
require_once __DIR__ . '/includes/ajax-handlers.php';
require_once __DIR__ . '/includes/bookings-filters.php';
require_once __DIR__ . '/includes/cart-order-hooks.php';
require_once __DIR__ . '/includes/cart-validation.php';
require_once __DIR__ . '/includes/debug.php';