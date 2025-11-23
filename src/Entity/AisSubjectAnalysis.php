<?php

namespace Drupal\indexing_study\Entity;

use Drupal\indexing_study\Entity\AbstractAisNode;
use Drupal\indexing_study\Entity\AisSubjectAnalysisInterface;
use Drupal\node\Entity\Node;

class AisSubjectAnalysis extends AbstractAisNode implements AisSubjectAnalysisInterface
{

  /**
   * @inheritDoc
   */
  public function getAssignment(): AisAssignmentInterface
  {
    return $this->get($this->config->get('subject_analysis.assignment_field'))->referencedEntities()[0];
  }
}
