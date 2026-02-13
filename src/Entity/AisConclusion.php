<?php

namespace Drupal\indexing_study\Entity;

use Drupal\indexing_study\Entity\AbstractAisNode;
use Drupal\indexing_study\Entity\AisConclusionInterface;

class AisConclusion extends AbstractAisNode implements AisConclusionInterface
{

  public function getDependents(): array
  {
    return [];
  }

  public function getDocument(): AisDocumentInterface {
    return $this->get($this->config()->get('conclusion.document_field'))->referencedEntities()[0];
  }
}
