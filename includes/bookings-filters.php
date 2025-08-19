<?php
if (!defined('ABSPATH')) exit;

// Filter to mark a date as fully booked for the destination if any source has a full day booking
add_filter('woocommerce_bookings_is_booking_fully_booked', function($fully_booked, $product_id, $date) {
    if ((int)$product_id === SSPG_DESTINATION_ID) {
        $group_ids = json_decode(SSPG_SOURCE_IDS, true);
        foreach ($group_ids as $src_id) {
            $bookings = get_posts([
                'post_type' => 'wc_booking',
                'post_status' => array('paid', 'confirmed'),
                'meta_query' => [
                    [
                        'key' => '_booking_product_id',
                        'value' => $src_id
                    ],
                    [
                        'key' => '_booking_start',
                        'value' => date('Ymd', $date),
                        'compare' => 'LIKE'
                    ]
                ]
            ]);
            foreach ($bookings as $booking) {
                if (sspg_infer_slot_key($booking->ID) === 'fullday') {
                    return true;
                }
            }
        }
    }
    return $fully_booked;
}, 10, 3);