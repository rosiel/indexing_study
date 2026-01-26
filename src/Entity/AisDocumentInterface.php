<?php
namespace Drupal\indexing_study\Entity;

use Drupal\user\UserInterface;


interface AisDocumentInterface extends AbstractAisNodeInterface {


  /**
   * Return the document's associated analyses as objects.
   *
   * @return array
   */
  public function getAnalyses(): array;

  /**
   * Return the associated Consensus node IDs.
   *
   * @return array
   */
  public function getConsensus(): array;

  /**
   * Return the associated Agreement Assignments as objects.
   *
   * @return array
   */
  public function getAgreementAssignments(): array;

  /**
   * Return the associated Agreements as objects.
   *
   * @return array
   */
  public function getAgreements(): array;

  /**
   * Return the associated study.
   *
   * @return AisStudyInterface
   */
  public function getStudy(): AisStudyInterface;

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

  public function createAssignment($userId): int|NULL;

  public function assignment_exists(UserInterface $user): bool;

}

