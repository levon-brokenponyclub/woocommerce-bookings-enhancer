<?php
if (!defined('ABSPATH')) exit;

// Example AJAX handler for slot availability (implement as needed)
add_action('wp_ajax_sspg_check_slot', 'sspg_ajax_check_slot');
add_action('wp_ajax_nopriv_sspg_check_slot', 'sspg_ajax_check_slot');

function sspg_ajax_check_slot() {
    $product_id = intval($_POST['product_id'] ?? 0);
    $date = sanitize_text_field($_POST['date'] ?? '');
    $slot = sanitize_text_field($_POST['slot'] ?? '');

    // Implement logic to check slot and return status
    wp_send_json_success(['available' => true]);
}