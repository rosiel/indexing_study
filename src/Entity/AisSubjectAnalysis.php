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
    return $this->get($this->config()->get('subject_analysis.assignment_field'))->referencedEntities()[0];
  }

  public function getConsensus(): array {
    $consensus_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type',$this->config()->get('consensus.bundle'))
      ->condition($this->config()->get('consensus.subject_analysis_field'), $this->id())
      ->execute();
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($consensus_ids);
  }

  public function getDependents(): array
  {
    return $this->computeDependents($this->config()->get('consensus.bundle'),
      $this->config()->get('consensus.subject_analysis_field'));
  }


  public function getDocument(): AisDocumentInterface
  {
    return $this->get($this->config()->get('subject_analysis.document_field'))->referencedEntities()[0];

  }
}
