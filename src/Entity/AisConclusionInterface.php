<?php

namespace Drupal\indexing_study\Entity;

interface AisConclusionInterface extends AbstractAisNodeInterface
{
  public function getDocument(): AisDocumentInterface;
}
