<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\node\Entity\Node;
use Drupal\Core\Logger\LoggerChannelInterface;
use Psr\Log\LoggerInterface;

class AbstractAisNode extends Node
{
  use MessengerTrait;
  /**
   * The Indexing Study Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig $config
   */
  protected ImmutableConfig $config;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The Indexing Study utils.
   *
   * @var \Drupal\indexing_study\IndexingStudyUtils
   */
  protected IndexingStudyUtils $utils;

  public function __construct(array $values, $entity_type, $bundle = FALSE, $translations = [])
  {
    parent::__construct($values, $entity_type, $bundle, $translations);
    $this->config = \Drupal::config('indexing_study.settings');
    $this->logger = \Drupal::service('logger.factory')->get('indexing_study');
    $this->utils = \Drupal::service('indexing_study.utils');
  }

  protected function config(): ImmutableConfig {
    if (!isset($this->config)) {
      $this->config = \Drupal::config('indexing_study.settings');
    }
    return $this->config;
  }

  protected function computeDependents($bundle, $field): array {
    $dependent_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition($field, $this->id())
      ->execute();
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($dependent_ids);
  }

  public static function preDelete(EntityStorageInterface $storage, array $entities)
  {
    parent::preDelete($storage, $entities);
    // Delete dependent entities.
    foreach($entities as $entity) {
      $dependents = $entity->getDependents();
      foreach ($dependents as $dependent) {
        $dependent->delete();
      }
    }
  }

  public static function compare_ids(object $obj1, object $obj2): int {
    return $obj1->id() <=> $obj2->id();
  }

}
