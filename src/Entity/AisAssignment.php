<?php

namespace Drupal\indexing_study\Entity;

class AisAssignment extends AbstractAisNode implements AisAssignmentInterface
{
  public function getDependents(): array
  {
    return $this->computeDependents($this->config->get('subject_analysis.bundle'),
      $this->config->get('subject_analysis.assignment_field'));
  }
}
