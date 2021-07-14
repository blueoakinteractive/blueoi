<?php

namespace Drupal\blueoi_commerce_admin_order\Plugin\Field\FieldWidget;

use Drupal\commerce\Context;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\PriceCalculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'blueoi_order_item_widget' widget.
 *
 * Inspired by PosOrderItemWidget as par of commerce_pos.
 *
 * @FieldWidget(
 *   id = "blueoi_order_item_widget",
 *   label = @Translation("BlueOI Order Item Widget"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = true
 * )
 */
class OrderItemWidget extends WidgetBase implements WidgetInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a EntityReferenceEntityFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'size' => 60,
        'placeholder' => 'Scan or enter product name or SKU',
        'num_results' => 20,
        'purchasable_entity_view_mode' => 'default',
        'allow_decimal' => FALSE,
        'decimal_step' => '0.1',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $elements['num_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of search results'),
      '#default_value' => $this->getSetting('num_results'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 50,
    ];
    $elements['purchasable_entity_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Purchasable entity view mode'),
      '#default_value' => $this->getSetting('purchasable_entity_view_mode'),
      '#required' => TRUE,
      // @todo use the "Purchasable entity type" from the order item type.
      '#options' => $this->entityDisplayRepository->getViewModeOptions('commerce_product_variation'),
      '#description' => $this->t('This view mode is used for the list of product variations to select from.'),
    ];
    $elements['allow_decimal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow decimal quantities'),
      '#default_value' => $this->getSetting('allow_decimal'),
    ];
    $elements['decimal_step'] = [
      '#type' => 'select',
      '#title' => $this->t('Step'),
      '#description' => $this->t('Only quantities that are multiples of the selected step will be allowed.'),
      '#default_value' => $this->getSetting('decimal_step'),
      '#options' => [
        '0.1' => '0.1',
        '0.01' => '0.01',
        '0.25' => '0.25',
        '0.5' => '0.5',
        '0.05' => '0.05',
      ],
      '#states' => [
        'visible' => [
          ':input[name="fields[order_items][settings_edit_form][settings][allow_decimal]"]' => ['checked' => TRUE],
        ],
      ],
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    $summary[] = $this->t('Number of results: @num_results', ['@num_results' => $this->getSetting('num_results')]);
    $summary[] = $this->t('Purchasable entity view mode: @view_mode', ['@view_mode' => $this->getSetting('purchasable_entity_view_mode')]);
    $summary[] = $this->t('Allow decimal quantities: @allow_decimal', ['@allow_decimal' => $this->getSetting('allow_decimal') ? 'Yes' : 'No']);
    if ($this->getSetting('allow_decimal')) {
      $summary[] = $this->t('Quantity step: @decimal_step', ['@decimal_step' => $this->getSetting('decimal_step')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // There's nothing to do here the order already has the references. They
    // have been added by static::addOrderItem().
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Determine which step we're in.
    $this->is_edit_order = $form_state->get('is_edit_order');
    // Determine the initial items on the order.
    $this->initial_items_on_order = $form_state->get('initial_items_on_order');

    if ($form_state->getTriggeringElement()) {
      $this->processFormSubmission($items, $form, $form_state);
    }
    $element = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#description' => $this->fieldDefinition->getDescription(),
      '#field_title' => $this->fieldDefinition->getLabel(),
      '#required' => FALSE,
    ] + $element;

    // Make a wrapper for the entire form.
    if (empty($form_state->wrapper_id)) {
      $wrapper_id = Html::getUniqueId(__CLASS__);
      $form['#prefix'] = '<div id="' . $wrapper_id . '">';
      $form['#suffix'] = '</div>';
    }
    else {
      $wrapper_id = $form_state->wrapper_id;
    }

    $element['product_selector'] = [
      '#type' => 'textfield',
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
      '#description' => $this->t('Enter a product name or SKU above to add it to the order.'),
      '#default_value' => NULL,
      '#autocomplete_route_name' => 'blueoi_commerce_admin_order.order_item_widget_autocomplete',
      '#autocomplete_route_parameters' => [
        // @todo use the "Purchasable entity type" from the order type.
        'entity_type' => 'commerce_product_variation',
        'view_mode' => $this->getSetting('purchasable_entity_view_mode'),
        'count' => $this->getSetting('num_results'),
      ],
      '#ajax' => [
        'event' => 'autocompleteclose',
        'callback' => [$this, 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
      ],
    ];

    // Render a list of products that have been added to the order.
    $referenced_entities = $items->referencedEntities();
    $element['order_items'] = [
      '#type' => 'table',
      '#required' => FALSE,
      '#header' => [
        $this->t('Product'),
        $this->t('Unit price'),
        $this->t('Quantity'),
        $this->t('Remove'),
      ],
    ];

    /** @var \Drupal\commerce_order\Entity\OrderItem $entity */
    foreach ($referenced_entities as $entity) {
      $item_form = $this->orderItemForm($entity, $wrapper_id);
      array_unshift($element['order_items'], $item_form);
    }

    $element['#default_value'] = $items->referencedEntities();
    $element['#required'] = FALSE;
    return [
      'target_id' => $element
    ];
  }

  /**
   * Creates a form for an order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItem $order_item
   *   The order item.
   * @param string $wrapper_id
   *   The wrapper ID to use for Ajax.
   *
   * @return array
   *   The order item form.
   */
  protected function orderItemForm(OrderItem $order_item, $wrapper_id) {
    $user = \Drupal::currentUser();
    $product = $order_item->getPurchasedEntity();

    // Determine if the quantity field should accept decimals.
    if ($this->getSetting('allow_decimal')) {
      $this->getSetting('decimal_step');
      // If decimals are allowed, get the step and precision values.
      $step = $this->getSetting('decimal_step');

      $quantity = $order_item->getQuantity();
    }
    else {
      $step = 1;
      $quantity = round($order_item->getQuantity(), 0);
    }

    // If we don't render product here the title appears in the wrong place.
    // @todo work out why and fix this.
    $view_builder = $this->entityTypeManager->getViewBuilder($order_item->getEntityTypeId());

    // Create a mock product for deleted product variation entities.
    if (empty($product)){
      $product = ProductVariation::create([
        'type' => 'default',
        'sku' => 'DELETED-ITEM',
        'title' => $this->t('DELETED ITEM (@label)', ['@label' => $order_item->label()]),
        'status' => 1,
        'price' => new Price((string) $order_item->getTotalPrice()->getNumber(), $order_item->getTotalPrice()->getCurrencyCode()),
      ]);
    }

    $product_render = $view_builder->view($product, $this->getSetting('purchasable_entity_view_mode'), $order_item->language()
      ->getId());
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    $unit_price = $order_item->getUnitPrice();

    $form = [
      '#element_validate' => [
        [$this, 'validate'],
      ],
      'purchasable_entity' => [
        '#type' => 'markup',
        '#markup' => \Drupal::service('renderer')->render($product_render),
      ],
      'unit_price' => [
        'unit_price' => [
          '#type' => 'commerce_price',
          '#title' => $this->t('Unit price'),
          '#title_display' => 'invisible',
          '#name' => 'update_unit_price_' . $product->id(),
          '#default_value' => $unit_price->toArray(),
          '#allow_negative' => TRUE,
          '#order_item_id' => $order_item->id(),
          '#disabled' => !$user->hasPermission('manage ' . $order_item->bundle() . ' commerce_order_item') ? TRUE : FALSE,
        ],
        'unit_price_hidden' => [
          '#type' => 'hidden',
          '#value' => $currency_formatter->format($unit_price->getNumber(), $unit_price->getCurrencyCode()),
          '#attributes' => [
            'class' => 'commerce-pos-customer-display-unit-price-hidden',
          ],
        ],
        'item_total_price_hidden' => [
          '#type' => 'hidden',
          '#value' => $currency_formatter->format($order_item->getTotalPrice()->getNumber(), $order_item->getTotalPrice()->getCurrencyCode()),
          '#attributes' => [
            'class' => 'commerce-pos-customer-display-item-total-price-hidden',
          ],
        ],
      ],
      'quantity' => [
        'quantity' => [
          '#title' => $this->t('Quantity'),
          '#title_display' => 'invisible',
          '#type' => 'number',
          '#step' => $step,
          '#size' => 4,
          '#min' => 0,
          '#default_value' => $quantity,
          '#attributes' => [
            'class' => [
              'blueoi-commerce-admin-order-quantity',
            ],
          ],
          '#order_item_id' => $order_item->id(),
        ],
        'quantity_hidden' => [
          '#type' => 'hidden',
          '#value' => $quantity,
        ],
      ],
      'remove' => [
        '#type' => 'checkbox',
        '#default_value' => FALSE,
      ],
      'order_item_id' => [
        '#type' => 'hidden',
        '#value' => $order_item->id(),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    return isset($element['target_id']) ? $element['target_id'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    foreach (array_keys(Element::children($element)) as $key) {
      $element[$key]['#required'] = FALSE;
    }
    $element['#required'] = FALSE;
    return $element;
  }

  /**
   * Validate callback for the element.
   *
   * Handles updating the order item on form submit.
   *
   * @param $element
   *   The form element.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function validate($element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    /** @var OrderInterface $order */
    $order = $form_state->getFormObject()->getEntity();
    /** @var OrderItemInterface $order_item_id */
    $order_item_id = $values['order_item_id'];
    $order_item = OrderItem::load($order_item_id);

    if (!empty($order_item)) {
      if ($values['quantity']['quantity'] > 0 && empty($values['remove'])) {
        $order_item
          ->setUnitPrice(new Price($values['unit_price']['unit_price']['number'], $values['unit_price']['unit_price']['currency_code']), TRUE)
          ->setQuantity($values['quantity']['quantity'])
          ->save();
      } else {

        // Force delete the order item reference to prevent
        // orphaned field data when there are validation errors.
        $connection = \Drupal::database();
        $connection->delete('commerce_order__order_items')
          ->condition('order_items_target_id', $order_item->id())
          ->execute();

        $order->removeItem($order_item);
        $order_item->delete();
      }
    }
  }

  /**
   * Process any user input before building the widget.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Values for this field.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function processFormSubmission(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $trigger_element = $form_state->getTriggeringElement();

    $order = FALSE;

    if ($trigger_element['#name'] === 'order_items[target_id][product_selector]') {
      $order = $this->addOrderItem($items, $form, $form_state);
    }

    if ($order) {
      // Update the order on the form.
      $form_object = $form_state->getFormObject();
      $form_object->setEntity($order);

      // Remove the user input as we no longer need it.
      $user_input = $form_state->getUserInput();
      unset($user_input['order_items']);
      $form_state->setUserInput($user_input);
    }
  }

  /**
   * Adds an order item from the autocomplete test field to the order.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Values for this field.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\commerce_order\Entity\Order|false
   *   The updated commerce order if it has been updated, or FALSE if not.
   */
  protected function addOrderItem(FieldItemListInterface $items, array &$form, FormStateInterface &$form_state) {
    // Loading the product variation object.
    $product_variation = ProductVariation::load($form_state->getValue([
      'order_items',
      'target_id',
      'product_selector',
    ]));

    // If we've not loaded a product variation then exit doing nothing.
    if (!$product_variation) {
      // There's nothing to do.
      return FALSE;
    }

    // If there is already an order item for this variation add to the quantity.
    $create_new_order_item = TRUE;
    $order = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
    foreach ($order->getItems() as $order_item) {
      if ($order_item->getPurchasedEntity()->id() === $product_variation->id() && $order_item->type->getValue()[0]['target_id'] == 'default'
      ) {
        $create_new_order_item = FALSE;
        $order_item
          ->setQuantity($order_item->getQuantity() + 1)
          ->save();
        break;
      }
    }

    if ($create_new_order_item) {
      // Create new order Item for adding into existing order.
      /** @var PriceCalculator $price_calculator */
      $price_calculator = \Drupal::service('commerce_order.price_calculator');
      $context = new Context($order->getCustomer(), $order->getStore());
      $order_item = OrderItem::create([
        'type' => 'default',
        'title' => $product_variation->label(),
        'purchased_entity' => $product_variation,
        'quantity' => 1,
        'unit_price' => $price_calculator->calculate($product_variation, 1, $context)->getCalculatedPrice(),
      ]);
      $order_item->save();

      // Adding the item into the Order.
      $order->addItem($order_item);
    }

    return $order;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(&$form, FormStateInterface $form_state) {
    // Anything on the form might have changed because we're updating the order.
    return $form;
  }

}
