<?php
/**
 * Cart validation: Prevent conflicting bookings in cart (mirror group, same date)
 */

if (!defined('ABSPATH')) exit;

add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id, $quantity, $variation_id = '', $variations = array()) {
    // Only apply to our mirror group
    if (!sspg_is_group_member($product_id)) return $passed;

    $slot_key = $_POST['sspg_booking_slot_key'] ?? '';
    $start_time = $_POST['sspg_booking_start_time'] ?? '';
    $date = '';
    sspg_log('CART VALIDATION: POST data: ' . json_encode($_POST));

    // Try to extract date from known fields
    if (
        !empty($_POST['wc_bookings_field_start_date_year']) &&
        !empty($_POST['wc_bookings_field_start_date_month']) &&
        !empty($_POST['wc_bookings_field_start_date_day'])
    ) {
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
        $date = sanitize_text_field($_POST['start_date']);
    } elseif (!empty($_POST['booking_date'])) {
        $date = sanitize_text_field($_POST['booking_date']);
    } else {
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

    // Gather all cart items in the mirror group for this date, including the new booking
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
    $cart_items_for_date[] = [
        'pid' => $product_id,
        'slot' => $slot_key,
    ];
    sspg_log("CART VALIDATION: Adding product $product_id, slot $slot_key, date $date. Cart items for date (including new): " . json_encode($cart_items_for_date));

    // Mirror logic: block additional booking for same date in the group
    if (count($cart_items_for_date) > 1) {
        sspg_log("CART VALIDATION: BLOCKED - Already a booking for this group/date. Product $product_id, slot $slot_key, date $date. Cart items: " . json_encode($cart_items_for_date));
        wc_add_notice(__('A booking for this room group and date is already in your cart. Please remove it before adding another booking for this date.'), 'error');
        return false;
    }

    sspg_log("CART VALIDATION: PASSED for product $product_id, slot $slot_key, date $date");
    return $passed;
}, 20, 5);