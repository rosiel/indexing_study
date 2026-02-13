<?php

namespace Drupal\indexing_study\Entity;


interface AisAgreementInterface extends AbstractAisNodeInterface
{
  public function getAgreementAssignment(): AisAgreementAssignmentInterface|NULL;

}
