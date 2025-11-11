<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\migrate\Plugin\migrate\process\ArrayBuild;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;

class AisDocument extends Node implements  AisDocumentInterface {

  public function getAnalyses(): array
  {
    return \Drupal::service('indexing_study.utils')->getAnalysesForDocumentId($this->id());
  }

  public function getConsensus(): array
  {
    return \Drupal::service('indexing_study.utils')->getConsensusForDocumentId($this->id());
  }


}
