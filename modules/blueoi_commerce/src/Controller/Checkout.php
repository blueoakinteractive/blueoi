<?php

namespace Drupal\blueoi_commerce\Controller;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Checkout.
 *
 * @package Drupal\bariani_commerce\Controller
 */
class Checkout extends ControllerBase {

  /**
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  protected $cartSession;


  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Checkout constructor.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   */
  public function __construct(CartSessionInterface $cart_session, CartProviderInterface $cart_provider) {
    $this->cartSession = $cart_session;
    $this->cartProvider = $cart_provider;
    $this->setOrder();
    $this->setAccount();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_session'),
      $container->get('commerce_cart.cart_provider')
    );
  }

  /**
   * Sets the order property.
   */
  protected function setOrder() {
    $carts = $this->cartProvider->getCarts();
    if (!empty($carts)) {
      $this->order = reset($carts);
    }
  }

  /**
   * Sets the account property.
   */
  protected function setAccount() {
    $this->account = \Drupal::currentUser()->getAccount();
  }

  /**
   * Route callback for /checkout.
   */
  public function Checkout() {
    // Redirect to the checkout step.
    return $this->redirect('commerce_checkout.form', ['commerce_order' => $this->order->id()]);
  }

  /**
   * Access callback for /checkout.
   *
   * See Drupal\commerce_checkout\Controller::checkAccess.
   */
  public function checkoutAccess() {
    if (!empty($this->order)) {
      if ($this->order->getState()->value == 'canceled') {
        return AccessResult::forbidden()->addCacheableDependency($this->order);
      }

      // The user can checkout only their own non-empty orders.
      if ($this->account->isAuthenticated()) {
        $customer_check = $this->account->id() == $this->order->getCustomerId();
      }
      else {
        $active_cart = $this->cartSession->hasCartId($this->order->id(), CartSession::ACTIVE);
        $completed_cart = $this->cartSession->hasCartId($this->order->id(), CartSession::COMPLETED);
        $customer_check = $active_cart || $completed_cart;
      }

      $access = AccessResult::allowedIf($customer_check)
        ->andIf(AccessResult::allowedIf($this->order->hasItems()))
        ->andIf(AccessResult::allowedIfHasPermission($this->account, 'access checkout'))
        ->addCacheableDependency($this->order);

      return $access;
    }


    return AccessResult::forbidden();
  }

}
