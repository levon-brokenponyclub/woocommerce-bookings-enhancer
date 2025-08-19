<?php
/**
 * Front-end UI (Buttons + CSS) for SSPG plugin.
 */

if (!defined('ABSPATH')) exit;

// Optional: enqueue a small CSS file if present
add_action('wp_enqueue_scripts', function () {
    $css_path = plugin_dir_path(__FILE__) . '../admin.css';
    if (file_exists($css_path)) {
        wp_enqueue_style('sspg-admin', plugin_dir_url(__FILE__) . '../admin.css', [], filemtime($css_path));
    }
});

// Render the slot buttons on booking products within our mirror group
add_action('woocommerce_before_add_to_cart_button', function () {
    if (!is_product()) return;
    global $product;
    if (!$product || !$product->is_type('booking')) return;
    if (!sspg_is_group_member($product->get_id())) return;

    // Load prices from admin panel if set
    $option_key = 'sspg_room_prices';
    $prices = get_option($option_key, []);
    $pid = $product->get_id();
    $options = [
        'morning'   => ['label' => 'Morning',   'start' => '09:00', 'end' => '12:00', 'price' => $prices[$pid]['morning'] ?? '110.00'],
        'afternoon' => ['label' => 'Afternoon', 'start' => '13:00', 'end' => '16:00', 'price' => $prices[$pid]['afternoon'] ?? '110.00'],
        'fullday'   => ['label' => 'Full Day',  'start' => '09:00', 'end' => '16:00', 'price' => $prices[$pid]['fullday'] ?? '190.00'],
    ];
    ?>
    <div class="booking-slot-options">
        <label><strong>Select a date, then choose your booking slot:</strong></label>
        <div id="sspg-slot-buttons" class="sspg-slot-buttons">
            <div class="sspg-slot-btn-container">
                <?php foreach ($options as $key => $opt): ?>
                    <button type="button" class="slot-btn"
                        data-key="<?php echo esc_attr($key); ?>"
                        data-start="<?php echo esc_attr($opt['start']); ?>"
                        data-end="<?php echo esc_attr($opt['end']); ?>"
                        data-price="<?php echo esc_attr($opt['price']); ?>">
                        <?php echo esc_html($opt['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <input type="hidden" name="sspg_booking_slot_key" id="sspg_booking_slot_key" value="">
        <input type="hidden" name="sspg_booking_start_time" id="sspg_booking_start_time" value="">
        <input type="hidden" name="sspg_booking_end_time"   id="sspg_booking_end_time"   value="">
        <div id="sspg-price-display" class="wc-bookings-booking-cost price sspg-price-display">
            Booking cost: 
            <strong><span class="woocommerce-Price-amount amount">
            <bdi><span class="woocommerce-Price-currencySymbol">£</span>
            <span class="sspg-price-value">0.00</span></bdi></span></strong>
        </div>
    </div>
    <?php
});