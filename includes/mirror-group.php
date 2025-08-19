<?php
if (!defined('ABSPATH')) exit;

/** Return the array of product IDs that mirror with 573 (destination + sources) */
function sspg_mirror_group() {
    $sources = json_decode(SSPG_SOURCE_IDS, true) ?: [];
    $group   = array_map('intval', array_merge([SSPG_DESTINATION_ID], $sources));
    return array_unique($group);
}

/** Is this product one of the group members? */
function sspg_is_group_member($product_id) {
    return in_array((int)$product_id, sspg_mirror_group(), true);
}