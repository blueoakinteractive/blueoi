<?php
/**
 * @file
 * Views integration.
 */

/**
 * Implements hook_views_data_alter().
 */
function blueoi_commerce_views_data_alter(array &$data) {
  $data['commerce_order']['blueoi_commerce_order_balance'] = array(
    'title' => t('Order Balance'),
    'field' => array(
      'title' => t('Order Balance'),
      'help' => t('Provides the order balance (blueoi)'),
      'id' => 'blueoi_commerce_order_balance',
    ),
  );
}
