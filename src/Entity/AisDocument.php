<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\migrate\Plugin\migrate\process\ArrayBuild;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

class AisDocument extends AbstractAisNode implements  AisDocumentInterface {

  public function getAnalyses(): array
  {
    $analysis_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('subject_analysis.bundle'))
      ->condition($this->config()->get('subject_analysis.document_field'), $this->id())
      ->execute();
    return \Drupal::service('indexing_study.utils')->intify_array($analysis_ids);
  }

  public function getConsensus(): array
  {
    $consensus_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $this->config()->get('consensus.bundle'))
      ->condition($this->config()->get('consensus.document_field'), $this->id())
      ->execute();
    return \Drupal::service('indexing_study.utils')->intify_array($consensus_ids);
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
    // TODO: Make these get methods on the document and study.
    $studies = $this->get($this->config->get('document.study_field'))->getValue();
    $study_id = $studies[0]['target_id'];
    $study = $this->entityTypeManager()->getStorage('node')->load($study_id);
    $users_in_study = array_column($study->get($this->config->get('study.reviewers_field'))->getValue(), 'target_id');
    if (!in_array($user->id(), $users_in_study)) {
      //$this->logger()->error("User " . $user->getAccountName() . " must be a member of the document's study.");
      return NULL;
    }
    // Test if assignment of document to user already exists.
    if ($this->assignment_exists($user)) {
//      $this->logger->error("Can't create duplicate assignment of @document to @user.", [
//        '@document' => $this->id(),
//        '@user' => $user->getAccountName()
//      ]);
      return NULL;
    }

    // Create assignment.
    $assignment = Node::create([
      'type' => $this->config->get('assignment.bundle'),
      'title' => 'Assignment of ' . $this->id() . ' to ' . $user->getAccountName()
    ]);
    $assignment->set($this->config->get('assignment.user_field'), ['target_id' => $user->id()]);
    $assignment->set($this->config->get('assignment.document_field'), ['target_id' => $this->id()]);
    try {
      $assignment->save();
      return $assignment->id();
    } catch (EntityStorageException $e) {
      $this->logger->error('Could not create assignment. Error: ' . $e);
      return NULL;
    }
  }

  public function assignment_exists(UserInterface $user): bool
  {
    $assignment_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $this->config->get('assignment.bundle'))
      ->condition($this->config->get('assignment.document_field'), $this->id())
      ->condition($this->config->get('assignment.user_field'), $user->id())
      ->execute();
    if (!empty($assignment_ids)) {
      return True;
    } else {
      return False;
    }
  }

}
