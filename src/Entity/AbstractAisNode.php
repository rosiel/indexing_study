<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;

class AbstractAisNode extends Node
{
  /**
   * The Indexing Study Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig $config
   */
  protected ImmutableConfig $config;

  public function __construct(array $values, $entity_type, $bundle = FALSE, $translations = [])
  {
    parent::__construct($values, $entity_type, $bundle, $translations);
    $this->config = \Drupal::config('indexing_study.settings');
  }

  protected function config() {
    if (!isset($this->config)) {
      $this->config = \Drupal::config('indexing_study.settings');
    }
    return $this->config;
  }

  protected function computeDependents($bundle, $field) {
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
