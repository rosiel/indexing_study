<?php

namespace Drupal\indexing_study\Entity;


interface AisAgreementInterface extends AbstractAisNodeInterface
{
  public function getAgreementAssignment(): AisAgreementAssignmentInterface|NULL;

  public function needsConclusion(): bool;

  public function getSibling(): AisAgreementInterface|false;

  public function generateConclusion(): void;
}
