<?php

namespace Drupal\indexing_study\Entity;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

interface AisStudyInterface extends AbstractAisNodeInterface {

  /**
   * Return the .
   *
   * @return array
   */
  public function getDocIdsAll(): array;

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

  public function getDocIdsCompleted(): array;

  /**
   * Return a list of document ids that are awaiting assignment.
   *
   * @return array
   */
  public function getDocIdsAwaitingAssignment(): array;

  public function getAssignmentsForAnalysis(): array;

  public function getConsensuses(): array;

  public function getAgreementAssignmentsAwaiting(): array;

  public function getDocsAwaitingAgreement(): array;

  public function getDocIdsAwaitingConsensus(): array;

  public function getDocIdsAwaitingConclusion(): array;

  public function getDocIdsRejected(): array;

  public function createAssignments(array $reviewers): bool;

  /**
   * @return array of user objects assigned to this study.
   */
  public function getReviewers(): array;

  public function getAgreementAssignments(): array;

}


