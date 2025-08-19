<?php
if (!defined('ABSPATH')) exit;

function sspg_log($msg) {
    if (!defined('SSPG_DEBUG') || !SSPG_DEBUG) return;
    @file_put_contents(__DIR__ . '/../debug.log', '[' . date('Y-m-d H:i:s') . "] {$msg}\n", FILE_APPEND);
}

register_activation_hook(__FILE__, function(){
    sspg_log('ACTIVATED: plugin activated and logging available.');
});
add_action('plugins_loaded', function(){
    sspg_log('BOOT: plugin file loaded');
});