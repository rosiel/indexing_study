<?php

namespace Drupal\indexing_study;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Build breadcrumbs for Indexing Study nodes.
 */
class IndexingStudyBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs the IndexingStudyBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
  }

  /**
   * See interface.
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    $config = $this->configFactory->get('indexing_study.settings');
    $applies_to = [
      $config->get('subject_analysis.bundle'),
      $config->get('consensus.bundle'),
      $config->get('agreement.bundle'),
    ];
    $cacheable_metadata?->addCacheContexts(['route']);
    if ($route_match->getRouteName() == 'node.add') {
      if (in_array($route_match->getParameter('node_type')->id(), $applies_to)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * See parent.
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $destination = $this->requestStack->getCurrentRequest()->query->get('destination');
    $destination_nid = preg_replace('/^\/study\/(\d+)\/[a-z_]*$/', '$1', $destination);
    if (!$destination_nid) {
      return $breadcrumb;
    }
    $destination_node = $this->entityTypeManager->getStorage('node')->load($destination_nid);
    if ($destination_node && $destination_node instanceof AisStudyInterface) {
      $breadcrumb->addLink(Link::createFromRoute($destination_node->getTitle(), 'indexing_study.study', ['study_node' => $destination_nid]));
    }
    $breadcrumb->addCacheableDependency($destination);
    return $breadcrumb;
  }

}
