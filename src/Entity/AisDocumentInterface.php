<?php
namespace Drupal\indexing_study\Entity;

interface AisDocumentInterface {

  /**
   * Return the document's associated analyses.
   *
   * @return array
   */
  public function getAnalyses(): array;

  /**
   * Return the total number of active documents in the study.
   *
   * @return array
   */
  public function getConsensus(): array;

}

