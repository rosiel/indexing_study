<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;
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

  protected function intify_array($array) {
    $return_array = [];
    foreach ($array as $value) {
      $return_array[] = (int) $value;
    }
    return $return_array;
  }
}
