<?php
if (!defined('ABSPATH')) exit;

/* ============================================================================
 * SERVER: CALENDAR “FULLY BOOKED” FILTER (authoritative)
 * ==========================================================================*/

add_filter('woocommerce_bookings_date_is_fully_booked', function (
    $is_fully_booked,
    $timestamp,
    $resource_id,
    $product
) {
    if (!$product) {
        return $is_fully_booked;
    }

    $pid      = (int) $product->get_id();
    $dest_id  = (int) SSPG_DESTINATION_ID;
    $sources  = json_decode(SSPG_SOURCE_IDS, true) ?: [];
    $date     = date('Y-m-d', $timestamp);

    if (!sspg_is_group_member($pid)) {
        return $is_fully_booked; // Not part of mirror group
    }

    // Which products should this one check against?
    if ($pid === $dest_id) {
        // Destination checks all sources
        $check_ids = $sources;
    } else {
        // Sources check themselves + destination
        $check_ids = [$pid, $dest_id];
    }

    // Find bookings on this date
    $booking_ids = get_posts([
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status'    => [
            'confirmed', 'paid', 'complete', 'unpaid', 'pending-confirmation'
        ],
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_booking_product_id',
                'value'   => $check_ids,
                'compare' => 'IN',
            ],
            [
                'key'     => '_booking_start',
                'value'   => $date,
                'compare' => 'LIKE',
            ],
        ],
    ]);

    $has_full = $has_m = $has_a = false;

    foreach ($booking_ids as $bid) {
        $bid_pid = (int) get_post_meta($bid, '_booking_product_id', true);
        $slot    = get_post_meta($bid, 'sspg_booking_slot_key', true) ?: sspg_infer_slot_key($bid);

        if ($pid === $dest_id) {
            // Destination aggregates sources
            if ($slot === 'fullday')   $has_full = true;
            if ($slot === 'morning')   $has_m    = true;
            if ($slot === 'afternoon') $has_a    = true;
        } else {
            // Source checks itself + destination
            if ($bid_pid === $pid || $bid_pid === $dest_id) {
                if ($slot === 'fullday')   $has_full = true;
                if ($slot === 'morning')   $has_m    = true;
                if ($slot === 'afternoon') $has_a    = true;
            }
        }
    }

    // Mirror logic: booking morning/afternoon blocks full day for that room and for 573 if booking is on 4856/4838
    $fully_booked_now = false;
    if ($has_full) {
        $fully_booked_now = true;
    } else {
        if ($pid === $dest_id) {
            if ($has_m || $has_a) {
                $fully_booked_now = true;
            }
        } elseif (in_array($pid, $sources)) {
            if ($has_m || $has_a) {
                $fully_booked_now = true;
            }
        }
    }

    sspg_log(sprintf(
        'FULLY_BOOKED filter: pid=%d date=%s full=%d morning=%d afternoon=%d => %s',
        $pid,
        $date,
        $has_full,
        $has_m,
        $has_a,
        $fully_booked_now ? 'BLOCKED' : 'AVAILABLE'
    ));

    return $fully_booked_now;
}, 999, 4);

/**
 * TEST HOOK — forcibly block a hard-coded date for destination product.
 * Remove when done testing.
 */
add_filter('woocommerce_bookings_get_blocks_in_range', function (
    $blocks,
    $from,
    $to,
    $bookable_product
) {
    $pid = $bookable_product ? (int) $bookable_product->get_id() : 0;
    sspg_log("BLOCKS hook fired: pid={$pid} from={$from} to={$to}");

    if ($pid === (int) SSPG_DESTINATION_ID) {
        $test_date = '2025-09-01';
        $ts        = strtotime($test_date);
        sspg_log("TEST: hard-wire FULL for {$pid} on {$test_date}");
        $blocks[$ts] = [];
    }

    return $blocks;
}, 10, 4);

/* ============================================================================
 * SERVER-SIDE CALENDAR CELL CLASSES (supports dashed + compact dates)
 * ==========================================================================*/
add_filter('woocommerce_bookings_get_day_class', function($classes, $date, $product) {
    $pid = $product ? (int) $product->get_id() : 0;
    $dest_id = (int) SSPG_DESTINATION_ID;
    $sources = json_decode(SSPG_SOURCE_IDS, true) ?: [];

    if (!sspg_is_group_member($pid)) {
        return $classes; // Not in our mirror group
    }

    $ymd_dashed  = date('Y-m-d', $date);
    $ymd_compact = date('Ymd', $date);

    // Destination looks at sources; sources look at self + destination
    $check_ids = ($pid === $dest_id) ? $sources : [$pid, $dest_id];

    $booking_ids = get_posts([
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status'    => ['confirmed','paid','complete','unpaid','pending-confirmation'],
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_booking_product_id',
                'value'   => $check_ids,
                'compare' => 'IN',
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => '_booking_start',
                    'value'   => $ymd_dashed,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_booking_start',
                    'value'   => $ymd_compact,
                    'compare' => 'LIKE',
                ],
            ],
        ],
    ]);

    $has_full = $has_m = $has_a = false;

    foreach ($booking_ids as $bid) {
        $bid_pid = (int) get_post_meta($bid, '_booking_product_id', true);
        $slot    = get_post_meta($bid, 'sspg_booking_slot_key', true) ?: sspg_infer_slot_key($bid);

        if ($pid === $dest_id) {
            // Destination: aggregate all slots from sources
            if ($slot === 'fullday')   $has_full = true;
            if ($slot === 'morning')   $has_m    = true;
            if ($slot === 'afternoon') $has_a    = true;
        } else {
            // Sources: only own + destination slots
            if ($bid_pid === $pid || $bid_pid === $dest_id) {
                if ($slot === 'fullday')   $has_full = true;
                if ($slot === 'morning')   $has_m    = true;
                if ($slot === 'afternoon') $has_a    = true;
            }
        }
    }

    // If any slot is booked, mark full day as booked
    if ($has_full || $has_m || $has_a) {
        $classes[] = 'full_day_booked';
    }
    if ($has_m) $classes[] = 'morning_booked';
    if ($has_a) $classes[] = 'afternoon_booked';

    return array_unique($classes);
}, PHP_INT_MAX, 3);