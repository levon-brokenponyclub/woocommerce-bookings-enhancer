<?php
if (!defined('ABSPATH')) exit;

/* ============================================================================
 * CART / ORDER: carry slot choices + simple pricing
 * ==========================================================================*/

add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    foreach (['sspg_booking_slot_key', 'sspg_booking_start_time', 'sspg_booking_end_time'] as $key) {
        if (isset($_POST[$key])) {
            $cart_item_data[$key] = sanitize_text_field($_POST[$key]);
        }
    }

    // Ensure uniqueness for same product with different slot
    if (!empty($cart_item_data['sspg_booking_slot_key'])) {
        $cart_item_data['unique_key'] = md5(
            $cart_item_data['sspg_booking_slot_key'] . microtime(true) . wp_rand()
        );
    }

    return $cart_item_data;
}, 10, 3);

/**
 * Restore slot data from session
 */
add_filter('woocommerce_get_cart_item_from_session', function ($cart_item, $values) {
    foreach (['sspg_booking_slot_key', 'sspg_booking_start_time', 'sspg_booking_end_time'] as $key) {
        if (isset($values[$key])) {
            $cart_item[$key] = $values[$key];
        }
    }
    return $cart_item;
}, 10, 2);

/**
 * Simple pricing:
 *  - £190.00 for full day
 *  - £110.00 for morning or afternoon
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Load prices from admin panel if set
    $option_key = 'sspg_room_prices';
    $prices = get_option($option_key, []);
    foreach ($cart->get_cart() as $item) {
        if (empty($item['sspg_booking_slot_key'])) {
            continue;
        }
        $product_id = $item['product_id'] ?? ($item['data']->get_id() ?? 0);
        $price_full = isset($prices[$product_id]['fullday']) ? floatval($prices[$product_id]['fullday']) : 190.00;
        $price_half = isset($prices[$product_id]['morning']) ? floatval($prices[$product_id]['morning']) : 110.00;
        if (in_array($item['sspg_booking_slot_key'], ['morning','afternoon'])) {
            $item['data']->set_price($price_half);
        } elseif ($item['sspg_booking_slot_key'] === 'fullday') {
            $item['data']->set_price($price_full);
        }
    }
});

/**
 * Show slot details at cart/checkout
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['sspg_booking_slot_key'])) {
        $item_data[] = [
            'name'  => 'Booking Slot',
            'value' => ucfirst($cart_item['sspg_booking_slot_key']),
        ];
    }
    if (!empty($cart_item['sspg_booking_start_time']) && !empty($cart_item['sspg_booking_end_time'])) {
        $item_data[] = [
            'name'  => 'Slot Time',
            'value' => $cart_item['sspg_booking_start_time'] . ' - ' . $cart_item['sspg_booking_end_time'],
        ];
    }
    return $item_data;
}, 10, 2);

/**
 * Save slot meta to the order line item
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (!empty($values['sspg_booking_slot_key'])) {
        $item->add_meta_data('Booking Slot', ucfirst($values['sspg_booking_slot_key']), true);
    }
    if (!empty($values['sspg_booking_start_time']) && !empty($values['sspg_booking_end_time'])) {
        $item->add_meta_data(
            'Slot Time',
            $values['sspg_booking_start_time'] . ' - ' . $values['sspg_booking_end_time'],
            true
        );
    }
}, 10, 3);

add_filter('woocommerce_cart_item_price', function($price_html, $cart_item, $cart_item_key) {
    // Load prices from admin panel if set
    $option_key = 'sspg_room_prices';
    $prices = get_option($option_key, []);
    $product_id = $cart_item['product_id'] ?? ($cart_item['data']->get_id() ?? 0);
    $price_full = isset($prices[$product_id]['fullday']) ? floatval($prices[$product_id]['fullday']) : 190.00;
    $price_half = isset($prices[$product_id]['morning']) ? floatval($prices[$product_id]['morning']) : 110.00;
    if (!empty($cart_item['sspg_booking_slot_key'])) {
        switch ($cart_item['sspg_booking_slot_key']) {
            case 'fullday':
                $price_html = wc_price($price_full);
                break;
            case 'morning':
            case 'afternoon':
                $price_html = wc_price($price_half);
                break;
        }
    }
    return $price_html;
}, 10, 3);

/* ============================================================================
 * CART VALIDATION: Prevent conflicting bookings in cart (mirror group, same date)
 * ==========================================================================*/

add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id, $quantity, $variation_id = '', $variations = array()) {
    // Only apply to our mirror group
    if (!sspg_is_group_member($product_id)) return $passed;

    $slot_key = $_POST['sspg_booking_slot_key'] ?? '';
    $start_time = $_POST['sspg_booking_start_time'] ?? '';
    $date = '';
    // Log all POST data for debugging
    sspg_log('CART VALIDATION: POST data: ' . json_encode($_POST));

    // Try to extract date from known fields
    if (
        !empty($_POST['wc_bookings_field_start_date_year']) &&
        !empty($_POST['wc_bookings_field_start_date_month']) &&
        !empty($_POST['wc_bookings_field_start_date_day'])
    ) {
        // Reconstruct date from split fields
        $year = str_pad($_POST['wc_bookings_field_start_date_year'], 4, '0', STR_PAD_LEFT);
        $month = str_pad($_POST['wc_bookings_field_start_date_month'], 2, '0', STR_PAD_LEFT);
        $day = str_pad($_POST['wc_bookings_field_start_date_day'], 2, '0', STR_PAD_LEFT);
        $date = "$year-$month-$day";
        sspg_log("CART VALIDATION: Reconstructed date from split fields: $date");
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $start_time)) {
        $date = substr($start_time, 0, 10);
    } elseif (!empty($_POST['wc_bookings_field_start_date'])) {
        $date = sanitize_text_field($_POST['wc_bookings_field_start_date']);
    } elseif (!empty($_POST['start_date'])) {
        // Try generic 'start_date' field
        $date = sanitize_text_field($_POST['start_date']);
    } elseif (!empty($_POST['booking_date'])) {
        // Try 'booking_date' field
        $date = sanitize_text_field($_POST['booking_date']);
    } else {
        // Try to find any date-like value in POST, but only check strings
        foreach ($_POST as $key => $val) {
            if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
                $date = substr($val, 0, 10);
                sspg_log("CART VALIDATION: Found date-like value in POST: $key = $val");
                break;
            }
        }
    }
    if (!$date) {
        sspg_log("CART VALIDATION: No date found for product $product_id, slot $slot_key after checking all POST fields");
        return $passed;
    }

    // Gather all cart items in the mirror group for this date, and include the new booking being added
    $mirror_group = sspg_mirror_group();
    $cart_items_for_date = [];
    foreach (WC()->cart->get_cart() as $cart_item) {
        $cart_pid = $cart_item['product_id'] ?? ($cart_item['data']->get_id() ?? 0);
        if (!in_array($cart_pid, $mirror_group, true)) continue;
        $cart_slot = $cart_item['sspg_booking_slot_key'] ?? '';
        $cart_start = $cart_item['sspg_booking_start_time'] ?? '';
        $cart_date = '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $cart_start)) {
            $cart_date = substr($cart_start, 0, 10);
        }
        // Also check for reconstructed date if available in cart item
        if (empty($cart_date) && !empty($cart_item['sspg_booking_date'])) {
            $cart_date = $cart_item['sspg_booking_date'];
        }
        if ($cart_date === $date) {
            $cart_items_for_date[] = [
                'pid' => $cart_pid,
                'slot' => $cart_slot,
            ];
        }
    }
    // Add the new booking being attempted to the array for validation
    $cart_items_for_date[] = [
        'pid' => $product_id,
        'slot' => $slot_key,
    ];
    sspg_log("CART VALIDATION: Adding product $product_id, slot $slot_key, date $date. Cart items for date (including new): " . json_encode($cart_items_for_date));

    // Mirror logic: block any additional booking for the same date in the group
    if (count($cart_items_for_date) > 1) {
        sspg_log("CART VALIDATION: BLOCKED - Already a booking for this group/date. Product $product_id, slot $slot_key, date $date. Cart items: " . json_encode($cart_items_for_date));
        wc_add_notice(__('A booking for this room group and date is already in your cart. Please remove it before adding another booking for this date.'), 'error');
        return false;
    }

    sspg_log("CART VALIDATION: PASSED for product $product_id, slot $slot_key, date $date");
    return $passed;
}, 20, 5);