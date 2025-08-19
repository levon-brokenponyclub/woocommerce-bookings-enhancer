<?php
if (!defined('ABSPATH')) exit;

function sspg_mirror_group() {
    $sources = json_decode(SSPG_SOURCE_IDS, true) ?: [];
    $group   = array_map('intval', array_merge([SSPG_DESTINATION_ID], $sources));
    return array_unique($group);
}

function sspg_is_group_member($product_id) {
    return in_array((int)$product_id, sspg_mirror_group(), true);
}