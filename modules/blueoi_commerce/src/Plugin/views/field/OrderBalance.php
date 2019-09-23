<?php
namespace Drupal\blueoi_commerce\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentOrderUpdaterInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler for order balance.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("blueoi_commerce_order_balance")
 */
class OrderBalance extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $balance = '';
    $order = $values->_entity;
    if (!empty($order) && $order instanceof OrderInterface) {
      /** @var PaymentOrderUpdaterInterface $order_updater */
      $payment_updater = \Drupal::service('commerce_payment.order_updater');
      $payment_updater->updateOrder($order);

      $order_balance = $order->getBalance();
      if (!empty($order_balance)) {
        $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
        $balance = $currency_formatter->format((string)$order_balance->getNumber(), $order_balance->getCurrencyCode());
      }
    }
    return $balance;
  }

}
