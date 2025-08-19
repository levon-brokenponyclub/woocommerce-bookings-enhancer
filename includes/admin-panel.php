<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page(
        'Bookings Enhancer',
        'Bookings Enhancer',
        'manage_options',
        'sspg-bookings-enhancer',
        'sspg_admin_panel_render',
        'dashicons-calendar-alt'
    );
});

function sspg_admin_panel_render() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Bookings Enhancer</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sspg_settings_group');
            do_settings_sections('sspg-bookings-enhancer');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('sspg_settings_group', 'sspg_destination_id');
    register_setting('sspg_settings_group', 'sspg_source_ids');

    add_settings_section(
        'sspg_settings_section',
        'Booking Mirror Group',
        function() { echo 'Configure which products are grouped for mirrored availability.'; },
        'sspg-bookings-enhancer'
    );

    add_settings_field(
        'sspg_destination_id',
        'Destination Product ID',
        function() {
            $val = esc_attr(get_option('sspg_destination_id', SSPG_DESTINATION_ID));
            echo "<input type='text' name='sspg_destination_id' value='{$val}' />";
        },
        'sspg-bookings-enhancer',
        'sspg_settings_section'
    );

    add_settings_field(
        'sspg_source_ids',
        'Source Product IDs (comma separated)',
        function() {
            $val = esc_attr(get_option('sspg_source_ids', implode(',', json_decode(SSPG_SOURCE_IDS, true))));
            echo "<input type='text' name='sspg_source_ids' value='{$val}' />";
        },
        'sspg-bookings-enhancer',
        'sspg_settings_section'
    );
});