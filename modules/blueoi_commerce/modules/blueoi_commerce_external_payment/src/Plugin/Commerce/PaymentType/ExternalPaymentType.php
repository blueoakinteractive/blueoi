<?php

namespace Drupal\blueoi_commerce_external_payment\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the external payment type.
 *
 * @CommercePaymentType(
 *   id = "blueoi_commerce_external_payment_type",
 *   label = @Translation("ExternalPaymentType"),
 *   workflow = "payment_manual",
 * )
 */
class ExternalPaymentType extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $fields['external_type'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('Store a reference with the payment'));
    $fields['external_reference'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Reference'))
      ->setDescription(t('Store a reference with the payment'));
    $fields['external_notes'] = BundleFieldDefinition::create('text')
      ->setLabel(t('Notes'))
      ->setDescription(t('Store a note with the payment'));
    return $fields;
  }

}
