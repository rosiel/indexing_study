<?php

namespace Drupal\indexing_study\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Sets some view pages to use the admin theme.
 */
class AdminViewsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.items.page_1')) {
      $route->setOption('_admin_route', 'TRUE');
    }
  }

}
