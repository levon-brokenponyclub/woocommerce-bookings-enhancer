<?php
if (!defined('ABSPATH')) exit;

function sspg_infer_slot_key($booking_id) {
    $slot = get_post_meta($booking_id, 'sspg_booking_slot_key', true);
    if ($slot) return $slot;

    $start = get_post_meta($booking_id, '_booking_start', true); 
    $end   = get_post_meta($booking_id, '_booking_end',   true);

    $sh = $eh = null;

    if ($start && strlen($start) === 14) {
        $sh = (int) substr($start, 8, 2);
    } elseif ($start && strlen($start) >= 16) {
        $sh = (int) substr($start, 11, 2);
    }

    if ($end && strlen($end) === 14) {
        $eh = (int) substr($end, 8, 2);
    } elseif ($end && strlen($end) >= 16) {
        $eh = (int) substr($end, 11, 2);
    }

    sspg_log("INFER SLOT: bid={$booking_id}, start={$start}, end={$end}, sh={$sh}, eh={$eh}");

    if ($sh === 9 && $eh === 16) return 'fullday';
    if ($sh >= 9  && $eh <= 12)  return 'morning';
    if ($sh >= 13 && $eh <= 16)  return 'afternoon';
    if ($sh === 0 && $eh >= 23)  return 'fullday';

    return 'fullday';
}