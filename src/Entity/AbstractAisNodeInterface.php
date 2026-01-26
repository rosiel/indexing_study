<?php

namespace Drupal\indexing_study\Entity;

use Drupal\node\NodeInterface;

interface AbstractAisNodeInterface extends NodeInterface
{
  public function getDependents(): array;

  public static function compare_ids(object $obj1, object $obj2): int;
}
