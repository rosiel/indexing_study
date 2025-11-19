<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;


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

  /**
   * List of user IDs who are or were assigned to this document.
   *
   * Includes users who rejected their assignments.
   * @return array
   */
  public function getAssignedUserIds(): array;

  /**
   * Whether or not this document needs assignment.
   */
  public function needsAssignment(): bool;

}

