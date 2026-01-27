<?php

namespace Drupal\indexing_study\Entity;

use Drupal\indexing_study\Entity\AbstractAisNodeInterface;
use Drupal\user\Entity\User;

interface AisAgreementAssignmentInterface extends AbstractAisNodeInterface
{
  public function isComplete(): bool;

  public function getUser(): User;

  public function getDocument(): AisDocumentInterface;

  public function getConsensus(): AisConsensusInterface;
}
