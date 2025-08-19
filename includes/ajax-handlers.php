<?php
if (!defined('ABSPATH')) exit;

/* ============================================================================
 * SERVER: BUTTONS AVAILABILITY (AJAX per-date)
 * ==========================================================================*/

add_action('wp_ajax_sspg_get_booking_slots', 'sspg_get_booking_slots');
add_action('wp_ajax_nopriv_sspg_get_booking_slots', 'sspg_get_booking_slots');
function sspg_get_booking_slots() {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $date       = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $scope      = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'group';

    if (!$product_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        sspg_log("AJAX slots — invalid params pid={$product_id}, date={$date}");
        wp_send_json_error('Missing or invalid parameters');
    }

    if (!sspg_is_group_member($product_id)) {
        sspg_log("AJAX slots — pid={$product_id} not in group, returning []");
        wp_send_json_success([]); // not in mirror system
    }

    $dest_id  = (int) SSPG_DESTINATION_ID;
    $sources  = json_decode(SSPG_SOURCE_IDS, true) ?: [];

    // Decide which bookings to query
    if ($product_id === $dest_id) {
        // Destination: look at all sources
        $ids = $sources;
    } else {
        // Source: look at itself + destination
        $ids = [$product_id, $dest_id];
    }

    $ymd_dashed  = $date;                 // e.g. 2025-09-30
    $ymd_compact = str_replace('-', '', $date); // e.g. 20250930

    // Query bookings for these IDs
    $booking_ids = get_posts([
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status'    => ['confirmed','paid','complete','unpaid','pending-confirmation'],
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_booking_product_id',
                'value'   => $ids,
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
        $bid_pid  = (int) get_post_meta($bid, '_booking_product_id', true);
        $start    = get_post_meta($bid, '_booking_start', true);
        $end      = get_post_meta($bid, '_booking_end', true);

        $slot_key = get_post_meta($bid, 'sspg_booking_slot_key', true);
        if (!$slot_key) {
            $slot_key = sspg_infer_slot_key($bid);
        }

        sspg_log("AJAX booking: bid={$bid}, pid={$bid_pid}, start={$start}, end={$end}, slot={$slot_key}");

        if ($product_id === $dest_id) {
            // Destination: aggregate all slots from sources
            if ($slot_key === 'fullday')   $has_full = true;
            if ($slot_key === 'morning')   $has_m    = true;
            if ($slot_key === 'afternoon') $has_a    = true;
        } else {
            // Source product
            if ($bid_pid === $product_id) {
                // Own bookings: use all slot types
                if ($slot_key === 'fullday')   $has_full = true;
                if ($slot_key === 'morning')   $has_m    = true;
                if ($slot_key === 'afternoon') $has_a    = true;
            } elseif ($bid_pid === $dest_id) {
                // Destination’s state mirrored into source
                if ($slot_key === 'fullday')   $has_full = true;
                if ($slot_key === 'morning')   $has_m    = true;
                if ($slot_key === 'afternoon') $has_a    = true;
            }
        }
    }

    $taken = [];
    if ($has_full) {
        $taken = ['fullday', 'morning', 'afternoon'];
    } else {
        if ($has_m) $taken[] = 'morning';
        if ($has_a) $taken[] = 'afternoon';
    }

    sspg_log(sprintf(
        'AJAX slots — FINAL date=%s product=%d ids=[%s] taken=[%s]',
        $date, $product_id, implode(',', $ids), implode(',', $taken)
    ));

    wp_send_json_success($taken);
}

/* ============================================================================
 * SERVER: MONTH FULL DAYS for UI mirroring (AJAX)
 * ==========================================================================*/

add_action('wp_ajax_sspg_get_month_full_days',     'sspg_get_month_full_days');
add_action('wp_ajax_nopriv_sspg_get_month_full_days', 'sspg_get_month_full_days');

if (!function_exists('sspg_normalize_start_to_ymd')) {
    /**
     * Normalise various _booking_start formats to YYYY-MM-DD
     */
    function sspg_normalize_start_to_ymd($raw) {
        if (!$raw) return '';
        $raw = trim($raw);

        // Compact 14 chars: YYYYMMDDHHMMSS
        if (preg_match('/^\d{14}$/', $raw)) {
            return sprintf('%s-%s-%s', substr($raw, 0, 4), substr($raw, 4, 2), substr($raw, 6, 2));
        }

        // Compact 8 chars: YYYYMMDD
        if (preg_match('/^\d{8}$/', $raw)) {
            return sprintf('%s-%s-%s', substr($raw, 0, 4), substr($raw, 4, 2), substr($raw, 6, 2));
        }

        // Datetime with dashes: YYYY-MM-DD HH:MM:SS
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        // Fallback: strtotime parse
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}

function sspg_get_month_full_days() {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $year       = isset($_POST['year'])       ? (int) $_POST['year']       : 0;
    $month      = isset($_POST['month'])      ? (int) $_POST['month']      : 0;

    if (
        !$product_id || 
        $product_id !== (int) SSPG_DESTINATION_ID || 
        $year < 2000 || 
        $month < 1 || $month > 12
    ) {
        sspg_log("AJAX month_full_days — invalid params pid={$product_id}, y={$year}, m={$month}");
        wp_send_json_error('Missing or invalid parameters');
    }

    $sources = json_decode(SSPG_SOURCE_IDS, true) ?: [];
    if (empty($sources)) {
        sspg_log("AJAX month_full_days — no sources configured");
        wp_send_json_success([]);
    }

    $month_start_dt  = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $month_end_dt    = date('Y-m-t 23:59:59', strtotime($month_start_dt));
    $month_start_num = date('YmdHis', strtotime($month_start_dt));
    $month_end_num   = date('YmdHis', strtotime($month_end_dt));

    sspg_log("DEBUG: Query for {$year}-{$month} :: DT {$month_start_dt}..{$month_end_dt} :: NUM {$month_start_num}..{$month_end_num}");

    $booking_ids = get_posts([
        'post_type'      => 'wc_booking',
        'posts_per_page' => -1,
        'post_status'    => ['confirmed','paid','complete','unpaid','pending-confirmation'],
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_booking_product_id',
                'value'   => array_map('intval', $sources),
                'compare' => 'IN',
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => '_booking_start',
                    'value'   => [$month_start_dt, $month_end_dt],
                    'compare' => 'BETWEEN',
                    'type'    => 'CHAR',
                ],
                [
                    'key'     => '_booking_start',
                    'value'   => [$month_start_num, $month_end_num],
                    'compare' => 'BETWEEN',
                    'type'    => 'CHAR',
                ],
            ],
        ],
    ]);

    if (empty($booking_ids)) {
        sspg_log("DEBUG: No bookings found for sources in {$year}-{$month}");
        wp_send_json_success([]);
    }

    foreach ($booking_ids as $bid) {
        $pid   = get_post_meta($bid, '_booking_product_id', true);
        $start = get_post_meta($bid, '_booking_start', true);
        $end   = get_post_meta($bid, '_booking_end', true);
        $slot  = get_post_meta($bid, 'sspg_booking_slot_key', true) ?: sspg_infer_slot_key($bid);
        sspg_log("SRC BID {$bid}: pid={$pid}, start={$start}, end={$end}, slot={$slot}");
    }

    $slots_per_day = [];
    foreach ($booking_ids as $bid) {
        $slot_key  = get_post_meta($bid, 'sspg_booking_slot_key', true) ?: sspg_infer_slot_key($bid);
        $start_raw = get_post_meta($bid, '_booking_start', true);
        $ymd       = sspg_normalize_start_to_ymd($start_raw);
        if (!$ymd) continue;

        if (!isset($slots_per_day[$ymd])) {
            $slots_per_day[$ymd] = ['morning' => false, 'afternoon' => false, 'fullday' => false];
        }
        if ($slot_key === 'fullday')   $slots_per_day[$ymd]['fullday']   = true;
        if ($slot_key === 'morning')   $slots_per_day[$ymd]['morning']   = true;
        if ($slot_key === 'afternoon') $slots_per_day[$ymd]['afternoon'] = true;
    }

    $full_days = [];
    foreach ($slots_per_day as $ymd => $slots) {
        $is_full = $slots['fullday'] || ($slots['morning'] && $slots['afternoon']);
        sspg_log(sprintf('MONTH mirror-check: %s slots=%s => %s', $ymd, json_encode($slots), $is_full ? 'FULL' : 'NOT FULL'));
        if ($is_full) $full_days[] = $ymd;
    }

    sspg_log(sprintf(
        'AJAX month_full_days — pid=%d y=%d m=%02d sources=[%s] full_days=[%s]',
        $product_id, $year, $month,
        implode(',', $sources),
        implode(',', $full_days)
    ));

    wp_send_json_success($full_days);
}