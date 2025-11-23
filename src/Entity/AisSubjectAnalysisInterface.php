<?php

namespace Drupal\indexing_study\Entity;

use Drupal\node\NodeInterface;

interface AisSubjectAnalysisInterface extends NodeInterface
{
  /**
   * Return the analysis' associated assignment.
   *
   * @return AisAssignmentInterface
   */
  public function getAssignment(): AisAssignmentInterface;
}
