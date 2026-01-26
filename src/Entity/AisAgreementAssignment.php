<?php

namespace Drupal\indexing_study\Entity;

use Drupal\user\Entity\User;

class AisAgreementAssignment extends AbstractAisNode implements AisAgreementAssignmentInterface
{

  public function getDependents(): array
  {
    return $this->computeDependents($this->config->get('agreement.bundle'),
      $this->config->get('agreement.agreement_assignment_field'));
  }

  public function isComplete(): bool
  {
    // There exists an Agreement that has this assignment linked.
    $agreement_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('agreement.bundle'))
      ->condition($this->config()->get('agreement.agreement_assignment_field'), $this->id())
      ->execute();
    if (count($agreement_ids)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  public function getUser(): User {
    return $this->get($this->config()->get('agreement_assignment.user_field'))->referencedEntities()[0];
  }
}
