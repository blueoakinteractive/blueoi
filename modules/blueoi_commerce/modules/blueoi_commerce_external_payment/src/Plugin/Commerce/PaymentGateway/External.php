<?php

namespace Drupal\blueoi_commerce_external_payment\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;

/**
 * Provides the external payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "blueoi_commerce_external_payment",
 *   label = "External",
 *   display_label = "External Payment",
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   },
 *   forms = {
 *     "add-payment" = "Drupal\blueoi_commerce_external_payment\PluginForm\ExternalPaymentAdd",
 *     "receive-payment" = "Drupal\commerce_payment\PluginForm\PaymentReceiveForm",
 *   },
 *   payment_type = "blueoi_commerce_external_payment_type",
 * )
 */
class External extends Manual {

}
