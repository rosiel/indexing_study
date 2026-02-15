<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Exception;
use Drupal\user\UserInterface;

class AisDocument extends AbstractAisNode implements  AisDocumentInterface {

  public function getAnalyses(): array
  {
    $storage = $this->entityTypeManager()->getStorage('node');
    $analysis_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('subject_analysis.bundle'))
      ->condition($this->config()->get('subject_analysis.document_field'), $this->id())
      ->execute();
    return $storage->loadMultiple($analysis_ids);
  }

  public function getConsensus(): AisConsensusInterface
  {
    $storage = $this->entityTypeManager()->getStorage('node');
    $consensus_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('consensus.bundle'))
      ->condition($this->config()->get('consensus.document_field'), $this->id())
      ->execute();
    $consensuses = $storage->loadMultiple($consensus_ids);
    if (count($consensuses) > 0) {
      $consensus = array_pop($consensuses);
      if (!($consensus instanceof AisConsensusInterface)) {
        throw new Exception("Consensus of the wrong bundle.");
      }
      return $consensus;
    }
    throw new Exception("Consensus not found for document {$this->id()}.");
  }

  public function getAgreementAssignments(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $agreement_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('agreement_assignment.bundle'))
      ->condition($this->config()->get('agreement_assignment.document_field'), $this->id())
      ->execute();
    return $storage->loadMultiple($agreement_ids);
  }

  public function getAgreements(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $agreement_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('agreement.bundle'))
      ->condition($this->config()->get('agreement.document_field'), $this->id())
      ->sort('created', 'ASC')
      ->execute();
    return $storage->loadMultiple($agreement_ids);
  }

  public function getStudy(): AisStudyInterface {
    return $this->get($this->config()->get('document.study_field'))->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedUserIds(): array {
    $assignment_ids = $this->getAssignments();
    $reviewers = [];
    foreach ($assignment_ids as $assignment_id) {
      $assignment = $this->entityTypeManager()->getStorage('node')->load($assignment_id);
      $reviewers[] = $assignment->get($this->config()->get('assignment.user_field'))->getValue()[0]['target_id'];
    }
    return $reviewers;
  }

  /**
   * {@inheritdoc }
   */
  public function getAssignments($valid = NULL): array{
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(False)
      ->condition('type', $this->config()->get('assignment.bundle'))
      ->condition($this->config()->get('assignment.document_field'), $this->id());
    if ($valid !== NULL) {
      switch($valid) {
        case (True):
          $query->condition('status', 1);
          break;
        case (False):
          $query->condition('status', 0);
      }
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function needsAssignment(): bool {
    // TODO: merge with study's needs assignment.
    $assignments = $this->getAssignments();
    if (count($assignments) < 2) {
      return True;
    }
    else {
      $valid = [];
      $invalid = [];
      foreach ($assignments as $assignment_id) {
        $assignment = $this->entityTypeManager()->getStorage('node')->load($assignment_id);
        if ($assignment->isPublished()) {
          $valid[] = $assignment_id;
        }
        else {
          $invalid[] = $assignment_id;
        }
      }
      if (count($invalid) >= 2 or count($valid) >= 2) {
        return False;
      }
    }
    return True;
  }

  public function createAssignment($userId): int|NULL {
    $user = $this->entityTypeManager()->getStorage('user')->load($userId);
    // Test if user is a member of the document's study.
    $study = $this->getStudy();
    $users_in_study = $study->getReviewers();
    if (!in_array($user, $users_in_study, TRUE)) {
      //$this->logger()->error("User " . $user->getAccountName() . " must be a member of the document's study.");
      return NULL;
    }
    // Test if assignment of document to user already exists.
    if ($this->assignment_exists($user)) {
      $this->logger->error("Can't create duplicate assignment of @document to @user.", [
        '@document' => $this->id(),
        '@user' => $user->getAccountName()
      ]);
      return NULL;
    }

    // Create assignment.
    $assignment = AisAssignment::create([
      'type' => $this->config()->get('assignment.bundle'),
      'title' => 'Assignment of ' . $this->id() . ' to ' . $user->getAccountName()
    ]);
    $assignment->set($this->config()->get('assignment.user_field'), ['target_id' => $user->id()]);
    $assignment->set($this->config()->get('assignment.document_field'), ['target_id' => $this->id()]);
    try {
      $assignment->save();
      return $assignment->id();
    } catch (EntityStorageException $e) {
      $this->logger->error('Could not create assignment. Error: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  public function assignment_exists(UserInterface $user): bool
  {
    $assignment_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $this->config()->get('assignment.bundle'))
      ->condition($this->config()->get('assignment.document_field'), $this->id())
      ->condition($this->config()->get('assignment.user_field'), $user->id())
      ->execute();
    if (!empty($assignment_ids)) {
      return True;
    } else {
      return False;
    }
  }

  public function getDependents(): array
  {
    return $this->computeDependents($this->config()->get('assignment.bundle'),
      $this->config()->get('assignment.document_field'));
  }

}
