<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('sspg-bookings-enhancer', plugins_url('css/sspg-bookings-enhancer.css', dirname(__FILE__)), [], '1.0');
});

add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!sspg_is_group_member($product->get_id())) return;

    ?>
    <div class="sspg-booking-slots">
        <label><input type="radio" name="sspg_slot" value="morning" required /> Morning</label>
        <label><input type="radio" name="sspg_slot" value="afternoon" /> Afternoon</label>
        <label><input type="radio" name="sspg_slot" value="fullday" /> Full Day</label>
    </div>
    <?php
});

add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
    if (isset($_POST['sspg_slot'])) {
        $cart_item_data['sspg_slot'] = sanitize_text_field($_POST['sspg_slot']);
    }
    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (isset($cart_item['sspg_slot'])) {
        $item_data[] = [
            'name' => 'Booking Slot',
            'value' => ucfirst($cart_item['sspg_slot'])
        ];
    }
    return $item_data;
}, 10, 2);