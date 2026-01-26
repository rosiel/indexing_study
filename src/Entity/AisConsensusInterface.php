<?php

namespace Drupal\indexing_study\Entity;

use Drupal\user\UserInterface;

interface AisConsensusInterface extends AbstractAisNodeInterface
{
  public function getDocument(): AisDocumentInterface;

  public function getStudy(): AisStudyInterface;

  public function generateAgreementAssignments(): void;

  public function createAgreementAssignment(UserInterface $user): AisAgreementAssignment;

  public function agreementAssignmentExists(UserInterface $user): bool;
}
