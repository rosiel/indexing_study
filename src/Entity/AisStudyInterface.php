<?php

namespace Drupal\indexing_study\Entity;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

interface AisStudyInterface extends AbstractAisNodeInterface {

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
  public function getAssignmentIdsForAnalysisByUser(UserInterface $user = NULL): array;

  /**
   * Return the ids of documents with the specified number of analyses done.
   *
   * @param $count
   *   The number of analyses; may be '0','1','2','>2', or NULL which defaults to '>=1'
   * @return array
   */
  public function getDocIdsByAnalysisCount($count = NULL): array;

  /**
   * Return a list of document ids that are awaiting assignment.
   *
   * @return array
   */
  public function getDocIdsAwaitingAssignment(): array;

  /**
   * Return the number of documents with analysis completed and no consensus.
   *
   * @return int
   */
  public function getDocCountAwaitingConsensus(): int;

  public function getDocIdsAwaitingConsensus(): array;

  public function getDocIdsRejected(): array;

  public function createAssignments(array $reviewers): bool;

  /**
   * @return array of user objects assigned to this study.
   */
  public function getReviewers(): array;


}


