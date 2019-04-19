<?php

namespace Drupal\blueoi_commerce_external_payment\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Rounder;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ExternalPaymentAdd.
 *
 * @package Drupal\blueoi_commerce_external_payment\PluginForm
 */
class ExternalPaymentAdd extends PaymentGatewayFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();
    if (!$order) {
      throw new \InvalidArgumentException('Payment entity with no order reference given to PaymentAddForm.');
    }

    // @todo: Make options customizable.
    $form['type'] = [
      '#type' => 'radios',
      '#title' => t('Payment Type'),
      '#options' => [
        'Credit Card' => t('Credit Card'),
        'ACH/Wire' => t('ACH/Wire'),
        'Check' => t('Check'),
        // 'Cash' => t('Cash'),
        'Split/Refund' => t('Split/Refund'),
        'Other' => t('Other'),
      ],
      '#required' => TRUE,
    ];

    /** @var Price $balance */
    $balance = $order->getBalance();
    if (!$balance instanceof Price || $balance->isNegative()) {
      $balance = new Price('0.00', $order->getStore()->getDefaultCurrencyCode());
    }

    $form['amount'] = [
      '#type' => 'commerce_price',
      '#allow_negative' => TRUE,
      '#title' => t('Amount'),
      '#default_value' => $order->getBalance()->toArray(),
      '#required' => TRUE,
    ];
    $form['date'] = [
      '#type' => 'datetime',
      '#title' => t('Date received'),
      '#default_value' => DrupalDateTime::createFromTimestamp(time()),
      '#required' => TRUE,
    ];
    $form['reference'] = [
      '#type' => 'textfield',
      '#title' => t('Reference'),
      '#description' => t('Enter a payment reference (e.g. Check Number)'),
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => t('Notes'),
      '#description' => t('Enter any notes about the payment'),
    ];
    $form['received'] = [
      '#type' => 'checkbox',
      '#title' => t('The specified amount was already received.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state)  {
    parent::validateConfigurationForm($form, $form_state);
    /** @var Rounder $rounder */
    $rounder = \Drupal::service('commerce_price.rounder');
    $amount = $form_state->getValue(['payment', 'amount']);

    // Validate a number is used for amount.
    if (!is_numeric($amount['number'])) {
      $form_state->setError($form['amount'], t('The must be a valid amount including decimal places'));
      return FALSE;
    }

    // Validate the rounded price is the same.
    $price = new Price((string) $amount['number'], $amount['currency_code']);
    if ($price->getNumber() != $rounder->round($price)->getNumber()) {
      $form_state->setError($form['amount'], t('The amount must be a valid number for @currency', ['@currency' => $amount['currency_code']]));
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $payment->amount = new Price((string) $values['amount']['number'], $values['amount']['currency_code']);
    $payment->set('external_type', [$values['type']]);
    $payment->set('external_reference', [$values['reference']]);
    $payment->set('external_notes', [$values['notes']]);
    $payment->set('completed', $values['date']->getTimestamp());
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    $payment_gateway_plugin->createPayment($payment, $values['received']);
  }

}
