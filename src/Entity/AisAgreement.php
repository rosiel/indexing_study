<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\indexing_study\Entity\AisConclusion;

class AisAgreement extends AbstractAisNode implements AisAgreementInterface
{

  public function getDependents(): array
  {
    return $this->computeDependents($this->config()->get('conclusion.bundle'),
      $this->config()->get('conclusion.agreement_field'));
  }

  public function getAgreementAssignment(): AisAgreementAssignmentInterface
  {
    return $this->get($this->config()->get('agreement.agreement_assignment_field'))->referencedEntities()[0];
  }

  public function getDocument(): AisDocumentInterface {
    return $this->get($this->config()->get('agreement.document_field'))->referencedEntities()[0];
  }

}
