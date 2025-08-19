<?php
/**
 * Light logger for SSPG plugin
 * Writes to debug.log in this plugin folder if SSPG_DEBUG is enabled
 */

if (!defined('ABSPATH')) exit;

define('SSPG_DEBUG', true);

/** Write a line to this plugin folder’s debug.log if SSPG_DEBUG is enabled */
function sspg_log($msg) {
    if (!SSPG_DEBUG) return;
    @file_put_contents(__DIR__ . '/../debug.log', '[' . date('Y-m-d H:i:s') . "] {$msg}\n", FILE_APPEND);
}

/** Prove plugin file loads + create debug.log on activation */
register_activation_hook(__FILE__, function(){
    sspg_log('ACTIVATED: plugin activated and logging available.');
});
add_action('plugins_loaded', function(){
    sspg_log('BOOT: plugin file loaded');
});