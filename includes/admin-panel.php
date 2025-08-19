<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page(
        'Conference Rooms',
        'Conference Rooms',
        'manage_options',
        'sspg_conference_rooms',
        'sspg_admin_panel_page',
        'dashicons-admin-multisite',
        56
    );
});

function sspg_admin_panel_page() {
    // --- Product IDs and labels (move to top so available everywhere) ---
    $product_ids = [573, 4856, 4838];
    $product_labels = [
        573 => 'Room 573',
        4856 => 'Room 4856',
        4838 => 'Room 4838',
    ];
    $slot_labels = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'fullday' => 'Full Day',
    ];

    // --- Category Dropdown ---
    $category_options = ['Seminar & Event Spaces'];
    $selected_category = $_POST['sspg_category'] ?? get_option('sspg_category', $category_options[0]);
    if (!empty($_POST['sspg_save_settings']) && check_admin_referer('sspg_save_settings')) {
        update_option('sspg_category', $selected_category);
    }

    // --- General/Availability/Costs Prefill ---
    $prefill = function($key, $default = '') use ($product_ids) {
        return isset($_POST[$key]) ? $_POST[$key] : (get_post_meta($product_ids[0], '_sspg_' . $key, true) ?: $default);
    };

    // --- Save Handler ---
    if (!empty($_POST['sspg_save_settings']) && check_admin_referer('sspg_save_settings')) {
        $fields = [
            'tax_status','tax_class','booking_duration_type','booking_duration_val','booking_duration_unit','calendar_display_mode',
            'max_bookings_per_block','min_block_bookable','min_block_unit','max_block_bookable','max_block_unit','buffer_period','buffer_unit','adjacent_buffering','all_dates','check_rules_against','restrict_days','days','range_type','range_from','range_to','range_bookable','range_priority',
            'base_cost','block_cost','display_cost'
        ];
        foreach ($product_ids as $pid) {
            foreach ($fields as $meta_key) {
                update_post_meta($pid, '_sspg_' . $meta_key, $_POST[$meta_key] ?? '');
            }
        }
        echo '<div class="updated"><p>Settings updated for all products (pricing unchanged).</p></div>';
    }

    // --- Settings Section ---
    echo '<hr><h2>Settings</h2>';
    echo '<form method="post">';
    wp_nonce_field('sspg_save_settings');
    echo '<table class="widefat fixed" style="max-width:700px"><thead><tr><th>Category</th></tr></thead><tbody>';
    echo '<tr><td>';
    echo '<select name="sspg_category">';
    foreach ($category_options as $cat) {
        echo '<option value="' . esc_attr($cat) . '"' . selected($selected_category, $cat, false) . '>' . esc_html($cat) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';
    echo '</tbody></table>';

    // --- General Section ---
    echo '<hr><h2>General</h2>';
    echo '<table class="widefat fixed" style="max-width:700px"><tbody>';
    echo '<tr><td>Tax status</td><td><input type="text" name="tax_status" value="' . esc_attr($prefill('tax_status','Taxable')) . '"></td></tr>';
    echo '<tr><td>Tax class</td><td><input type="text" name="tax_class" value="' . esc_attr($prefill('tax_class','Standard')) . '"></td></tr>';
    echo '<tr><td>Booking duration</td><td>';
    echo '<select name="booking_duration_type"><option value="fixed" selected>Fixed blocks of</option></select> ';
    echo '<input type="number" name="booking_duration_val" value="' . esc_attr($prefill('booking_duration_val','1')) . '" style="width:60px"> ';
    echo '<select name="booking_duration_unit"><option value="day" selected>Day(s)</option></select>';
    echo '</td></tr>';
    echo '<tr><td>Calendar display mode</td><td><input type="text" name="calendar_display_mode" value="' . esc_attr($prefill('calendar_display_mode','Calendar always visible')) . '"></td></tr>';
    echo '</tbody></table>';

    // --- Availability Section ---
    echo '<hr><h2>Availability</h2>';
    echo '<table class="widefat fixed" style="max-width:700px"><tbody>';
    echo '<tr><td>Max bookings per block</td><td><input type="number" name="max_bookings_per_block" value="' . esc_attr($prefill('max_bookings_per_block','1')) . '"></td></tr>';
    echo '<tr><td>Minimum block bookable</td><td><input type="number" name="min_block_bookable" value="' . esc_attr($prefill('min_block_bookable','1')) . '" style="width:60px"> <select name="min_block_unit"><option value="hour" selected>Hour(s)</option></select></td></tr>';
    echo '<tr><td>Maximum block bookable</td><td><input type="number" name="max_block_bookable" value="' . esc_attr($prefill('max_block_bookable','12')) . '" style="width:60px"> <select name="max_block_unit"><option value="month" selected>Month(s)</option></select></td></tr>';
    echo '<tr><td>Require a buffer period of</td><td><input type="number" name="buffer_period" value="' . esc_attr($prefill('buffer_period','0')) . '" style="width:60px"> <select name="buffer_unit"><option value="day" selected>days</option></select></td></tr>';
    echo '<tr><td>Adjacent Buffering?</td><td><input type="checkbox" name="adjacent_buffering" value="1"' . ($prefill('adjacent_buffering','') ? ' checked' : '') . '> By default buffer period applies forward into the future of a booking.</td></tr>';
    echo '<tr><td>All dates are...</td><td><input type="text" name="all_dates" value="' . esc_attr($prefill('all_dates','available by default')) . '"></td></tr>';
    echo '<tr><td>Check rules against...</td><td><input type="text" name="check_rules_against" value="' . esc_attr($prefill('check_rules_against','All blocks being booked')) . '"></td></tr>';
    echo '<tr><td>Restrict selectable days?</td><td><input type="checkbox" name="restrict_days" value="1"' . ($prefill('restrict_days','1') ? ' checked' : '') . '> Restrict the days of the week that are able to be selected on the calendar.</td></tr>';
    echo '<tr><td>Days</td><td>';
    $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $selected_days = $prefill('days','Monday,Tuesday,Wednesday,Thursday,Friday');
    if (is_array($selected_days)) $selected_days = implode(',', $selected_days);
    $selected_days_arr = array_map('trim', explode(',', $selected_days));
    foreach ($days as $d) {
        echo '<label style="margin-right:10px"><input type="checkbox" name="days[]" value="' . $d . '"' . (in_array($d, $selected_days_arr) ? ' checked' : '') . '> ' . $d . '</label>';
    }
    echo '</td></tr>';
    echo '<tr><td>Range type</td><td><select name="range_type"><option value="date" selected>Date range</option></select></td></tr>';
    echo '<tr><td>Range from</td><td><input type="text" name="range_from" value="' . esc_attr($prefill('range_from','09:00')) . '"></td></tr>';
    echo '<tr><td>Range to</td><td><input type="text" name="range_to" value="' . esc_attr($prefill('range_to','16:00')) . '"></td></tr>';
    echo '<tr><td>Range bookable</td><td><select name="range_bookable"><option value="yes" selected>Yes</option></select></td></tr>';
    echo '<tr><td>Range priority</td><td><input type="number" name="range_priority" value="' . esc_attr($prefill('range_priority','10')) . '"></td></tr>';
    echo '</tbody></table>';

    // --- Costs Section ---
    echo '<hr><h2>Costs</h2>';
    echo '<table class="widefat fixed" style="max-width:700px"><tbody>';
    echo '<tr><td>Base cost</td><td><input type="number" step="0.01" name="base_cost" value="' . esc_attr($prefill('base_cost','0')) . '"></td></tr>';
    echo '<tr><td>Block cost</td><td><input type="number" step="0.01" name="block_cost" value="' . esc_attr($prefill('block_cost','0')) . '"></td></tr>';
    echo '<tr><td>Display cost</td><td><input type="number" step="0.01" name="display_cost" value="' . esc_attr($prefill('display_cost','0')) . '"></td></tr>';
    echo '</tbody></table>';

    echo '<p><input type="submit" class="button button-primary" name="sspg_save_settings" value="Save Settings (Apply to All)"></p>';
    echo '</form>';

    // -- Continue with the pricing, test booking, etc. from pasted2.txt --
    // (If you want this entire admin page function with every last line of HTML, let me know!)
}