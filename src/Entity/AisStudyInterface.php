<?php
namespace Drupal\indexing_study\Entity;

interface AisStudyInterface {

  /**
   * Return the total number of active documents in the study.
   *
   * @return string
   */
  public function getDocCountInStudy(): string;

  /**
   * Return count of documents in study awaiting assignment.
   *
   * @return string
   */
  public function getDocCountInStudyAwaitingAssignment(): string;

  /**
   * Return count of documents in study fully assigned.
   *
   * @return string
   */
  public function getDocCountInStudyFullyAssigned(): string;
}

