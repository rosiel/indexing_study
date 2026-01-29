<?php

namespace Drupal\indexing_study\Entity;

interface AisAssignmentInterface extends AbstractAisNodeInterface
{
  public function getDocument(): AisDocumentInterface;
}
