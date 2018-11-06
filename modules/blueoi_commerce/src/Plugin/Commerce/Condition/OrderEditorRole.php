<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the order editor role condition for orders.
 *
 * @CommerceCondition(
 *   id = "blueoi_commerce_order_editor_role",
 *   label = @Translation("Order editor role"),
 *   category = @Translation("Editor"),
 *   entity_type = "commerce_order",
 * )
 */
class OrderEditorRole extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'roles' => [],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed roles'),
      '#default_value' => $this->configuration['roles'],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names()),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['roles'] = array_filter($values['roles']);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $editor = \Drupal::currentUser();
    return (bool) array_intersect($this->configuration['roles'], $editor->getRoles());
  }

}
