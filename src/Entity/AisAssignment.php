<?php

namespace Drupal\indexing_study\Entity;

class AisAssignment extends AbstractAisNode implements AisAssignmentInterface
{
  public function getDependents(): array
  {
    return $this->computeDependents($this->config()->get('subject_analysis.bundle'),
      $this->config()->get('subject_analysis.assignment_field'));
  }

  public function isCompleted(): bool {
    // There exists a subject Analysis that has this assignment linked.
    $agreement_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
    ->accessCheck(TRUE)
    ->condition('status', 1)
    ->condition('type', $this->config()->get('subject_analysis.bundle'))
    ->condition($this->config()->get('subject_analysis.assignment_field'), $this->id())
    ->execute();
    if (count($agreement_ids)) {
    return TRUE;
    } else {
      return FALSE;
    }
  }

  public function getDocument(): AisDocumentInterface {
    return $this->get($this->config()->get('assignment.document_field'))->referencedEntities()[0];
  }
}
