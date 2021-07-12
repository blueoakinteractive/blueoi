<?php

namespace Drupal\blueoi_commerce_admin_order\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_product\Entity\ProductVariation;

/**
 * Class PosOrderItemAutoComplete.
 *
 * Inspired by PosOrderItemAutoComplete as part of commerce_pos.
 *
 * @package Drupal\blueoi_commerce_admin_order\Controller
 */
class OrderItemAutoComplete extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new POS object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStore = $temp_store_factory->get('blueoi_commerce_admin_order');
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('renderer')
    );
  }

  /**
   * Order item auto complete handler.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $entity_type
   *   The entity type.
   * @param string $view_mode
   *   The view mode.
   * @param int $count
   *   The number of entities to list.
   *
   * @return object
   *   The Json object for auto complete suggestions.
   */
  public function orderItemAutoCompleteHandler(Request $request, $entity_type, $view_mode, $count) {
    $results = [];
    if ($input = $request->query->get('q')) {
      $ids = $this->queryVariations($input);
      $view_builder = $this->entityTypeManager->getViewBuilder($entity_type);
      foreach ($ids as $id) {
        $product_variation = ProductVariation::load($id);
        $product_render_array = $view_builder->view($product_variation, $view_mode, $product_variation->language()->getId());
        $render = $this->renderer->renderPlain($product_render_array);
        $item = Html::decodeEntities(strip_tags((string) $render));
        $results[] = [
          'value' => $product_variation->id(),
          'label' => $item,
        ];
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Query the database for matching products.
   *
   * @param string $input
   *   The search string.
   *
   * @return array
   *   The variations ids.
   */
  public function queryVariations($input) {
    $query = \Drupal::entityQuery('commerce_product_variation');
    $or = $query->orConditionGroup();
    $or->condition('sku', '%' . $input . '%', 'LIKE');
    $or->condition('title', '%' . $input . '%', 'LIKE');
    $query->condition($or);
    $query->condition('status', 1);
    $query->range(0, 20);
    $query->sort('title', 'ASC');
    \Drupal::moduleHandler()->alter('blueoi_commerce_admin_order_order_item_variation_query', $query, $input);
    return $query->execute();
  }

}
