<?php

namespace Drupal\indexing_study\Entity;

use Drupal\node\NodeInterface;

interface AisStudyInterface extends NodeInterface {

  /**
   * Return the total number of active documents in the study.
   *
   * @return int
   */
  public function getDocCount(): int;

  /**
   * Return the total number of documents with agreements in the study.
   *
   * @return int
   */
  public function getDocCountCompleted(): int;

  /**
   * Return count of documents in study awaiting assignment.
   *
   * @return int
   */
  public function getDocCountAwaitingAssignment(): int;

  /**
   * Return count of documents in study fully assigned.
   *
   * @return int
   */
  public function getDocCountFullyAssigned(): int;

  /**
   * Return count of assignments in study that need analysis by the current user.
   *
   * @return int
   */
  public function getAssignmentCountForAnalysis(): int;

  /**
   * Return the IDs of assignment ready for analysis by the current user.
   *
   * @return array
   */
  public function getAssignmentIdsForAnalysis(): array;

  /**
   * Return the count of documents with no subject analyses.
   *
   * @return int
   */
  public function getDocCountWith0Analyses(): int;

  /**
   * Return the count of documents with no subject analyses.
   *
   * @return int
   */
  public function getDocCountWith1Analysis(): int;

  /**
   * Return the count of documents with no subject analyses.
   *
   * @return int
   */
  public function getDocCountWith2Analyses(): int;

  /**
   * Return the count of documents with no subject analyses.
   *
   * @return int
   */
  public function getDocCountWithOver2Analyses(): int;

  /**
   * Return a list of document ids that are awaiting assignment.
   *
   * @return array
   */
  public function getDocIdsAwaitingAssignment(): array;

  /**
   * Return the number of total documents awaiting analysis.
   * @return int
   */
  public function getDocCountAwaitingAnalysis(): int;

  /**
   * Return the number of documents with analysis completed and no consensus.
   *
   * @return int
   */
  public function getDocCountAwaitingConsensus(): int;

  /**
   * Return the number of documents with consensus completed and no agreement.
   *
   * @return int
   */

  public function getDocCountAwaitingAgreement(): int;

}


