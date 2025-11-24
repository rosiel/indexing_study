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

  protected function intify_array($array): array {
    $return_array = [];
    foreach ($array as $value) {
      $return_array[] = (int) $value;
    }
    return $return_array;
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
    // Delete related entities.
    foreach($entities as $entity) {
      $dependents = $entity->getDependents();
      foreach ($dependents as $dependent) {
        $dependent->delete();
      }
    }
  }
}
