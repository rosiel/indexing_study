<?php
namespace Drupal\indexing_study\Entity;

interface AisStudyInterface {

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
   * @return int
   */
  public function getAssignmentCountForAnalysis(): int;

  /**
   * Return the IDs of assignment ready for analysis by the current user.
   *
   * @return array
   */
  public function getAssignmentIdsForAnalysis(): array;
}


