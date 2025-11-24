<?php

namespace Drupal\indexing_study\Entity;

use Drupal\node\NodeInterface;

interface AbstractAisNodeInterface extends NodeInterface
{
  public function getDependents(): array;
}
