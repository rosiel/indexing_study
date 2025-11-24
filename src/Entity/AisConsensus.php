<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityStorageInterface;

class AisConsensus extends AbstractAisNode implements AisConsensusInterface
{
  public function getDependents(): array
  {
    return $this->computeDependents($this->config->get('agreement.bundle'),
      $this->config->get('agreement.consensus_field'));
  }

}
