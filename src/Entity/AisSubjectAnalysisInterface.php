<?php

namespace Drupal\indexing_study\Entity;


interface AisSubjectAnalysisInterface extends AbstractAisNodeInterface
{
  /**
   * Return the analysis' associated assignment.
   *
   * @return AisAssignmentInterface
   */
  public function getAssignment(): AisAssignmentInterface;
}
