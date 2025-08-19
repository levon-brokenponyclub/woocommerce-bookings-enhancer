<?php
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    ?>
    <script>
    (function($){
        $(document).on('change', '.sspg-booking-slots input[type=radio]', function() {
            // Add your dynamic JS logic here, for example:
            // Show/hide descriptions or update prices based on slot selection.
        });
    })(jQuery);
    </script>
    <?php
});