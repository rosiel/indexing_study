<?php

namespace Drupal\indexing_study;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\indexing_study\Entity\AisAgreementAssignmentInterface;
use Drupal\indexing_study\Entity\AisAgreementInterface;
use Drupal\indexing_study\Entity\AisAssignmentInterface;
use Drupal\indexing_study\Entity\AisConclusionInterface;
use Drupal\indexing_study\Entity\AisConsensusInterface;
use Drupal\indexing_study\Entity\AisDocumentInterface;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Drupal\indexing_study\Entity\AisSubjectAnalysisInterface;
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
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs the IndexingStudyBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config for Indexing Study.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->config = $config_factory->get('indexing_study.settings');

  }

  /**
   * See interface.
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    $applies_to = [
      $this->config->get('document.bundle'),
      $this->config->get('subject_analysis.bundle'),
      $this->config->get('consensus.bundle'),
      $this->config->get('agreement_assignment.bundle'),
      $this->config->get('agreement.bundle'),
      $this->config->get('conclusion.bundle'),
    ];
    $cacheable_metadata?->addCacheContexts(['route']);
    if ($route_match->getRouteName() == 'node.add') {
      if (in_array($route_match->getParameter('node_type')->id(), $applies_to)) {
        return TRUE;
      }
    }
    if ($route_match->getRouteName() == 'entity.node.canonical') {
      if (in_array($route_match->getParameter('node')->bundle(), $applies_to)) {
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
    $breadcrumb->addLink(Link::createFromRoute($this->t('All Studies'), '<front>'));
    $destination = $this->requestStack->getCurrentRequest()->query->get('destination');
    if ($destination) {
      $destination_nid = preg_replace('/^\/study\/(\d+)\/[a-z_]*$/', '$1', $destination);
      if (!$destination_nid) {
        return $breadcrumb;
      }
      $destination_node = $this->entityTypeManager->getStorage('node')->load($destination_nid);
      if ($destination_node && $destination_node instanceof AisStudyInterface) {
        $breadcrumb->addLink(Link::createFromRoute($destination_node->getTitle(), 'indexing_study.study', ['study_node' => $destination_nid]));
      }
      $breadcrumb->addCacheableDependency($destination);
    }
    else {
      $entity = $route_match->getParameter('node');
      if ($entity instanceof AisDocumentInterface) {
        $breadcrumb = $this->addDocumentLinks($breadcrumb, $entity, false);
      }
      if ($entity instanceof AisAssignmentInterface
        or $entity instanceof AisSubjectAnalysisInterface
        or $entity instanceof AisConsensusInterface
        or $entity instanceof AisAgreementAssignmentInterface
        or $entity instanceof AisAgreementInterface
        or $entity instanceof AisConclusionInterface) {
        $document = $entity->getDocument();
        $breadcrumb = $this->addDocumentLinks($breadcrumb, $document, false);
      }
    }
    return $breadcrumb;
  }

  protected function addDocumentLinks($breadcrumb, $document, $self_link = true): Breadcrumb {
    $study = $document->getStudy();
    $breadcrumb->addLink(Link::createFromRoute($study->getTitle(), 'indexing_study.study', ['study_node' => $study->id()]));
    if ($self_link) {
      $breadcrumb->addLink(Link::createFromRoute($document->getTitle(), 'entity.node.canonical', ['node' => $document->id()]));
    }
    return $breadcrumb;
  }

}
