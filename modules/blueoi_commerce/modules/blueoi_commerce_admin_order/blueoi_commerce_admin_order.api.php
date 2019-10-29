<?php


/**
 * Implements HOOK_blueoi_commerce_admin_order_order_item_variation_query().
 */
function blueoi_commerce_admin_order_blueoi_commerce_admin_order_order_item_variation_query(&$query, $input) {
  $query->condition('status', 1);
}
