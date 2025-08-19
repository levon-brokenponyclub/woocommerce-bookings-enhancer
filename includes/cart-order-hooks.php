<?php
if (!defined('ABSPATH')) exit;

// Save slot info to booking post
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (isset($values['sspg_slot'])) {
        $item->add_meta_data('Booking Slot', ucfirst($values['sspg_slot']));
    }
}, 10, 4);

// Attach slot to booking meta
add_action('woocommerce_new_booking', function($booking_id, $args, $booking) {
    if (isset($args['order_item_id'])) {
        $slot = wc_get_order_item_meta($args['order_item_id'], 'Booking Slot', true);
        if ($slot) {
            update_post_meta($booking_id, 'sspg_booking_slot_key', strtolower($slot));
        }
    }
}, 10, 3);