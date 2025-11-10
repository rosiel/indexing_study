<?php

namespace Drupal\indexing_study;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

class IndexingStudyUtils
{
  // The machine name of the 'study' content type.
  const STUDY_BUNDLE = 'ais_study';
  // The machine name of the 'assignment' content type.
  const ASSIGNMENT_BUNDLE = 'ais_assignment';
  // The machine name of the 'document' content type.
  const DOCUMENT_BUNDLE = 'ais_document';
  // The machine name of the 'subject analysis' content type.
  const SUBJECT_ANALYSIS_BUNDLE = 'ais_subject_analysis';
  // The machine name of the 'consensus' content type.
  const CONSENSUS_BUNDLE = 'ais_consensus';
  // The machine name of the 'agreement' content type.
  const AGREEMENT_BUNDLE = 'ais_agreement';
  // The field on a study that points to the users/reviewers.
  const STUDY_REVIEWERS_FIELD = 'field_ais_participants';
  // The field on a document that points to the study.
  const DOCUMENT_STUDY_FIELD = 'field_ais_study';
  // Store the field on

  // Store the field on assignment that points to user.
  const ASSIGNMENT_USER_FIELD = 'field_ais_reviewer';
  // Store the field on assignment that points to a citation item.
  const ASSIGNMENT_DOCUMENT_FIELD = 'field_ais_document';
  // Store the field on a subject analysis that points to an assignment
  const SUBJECT_ANALYSIS_ASSIGNMENT_FIELD = 'field_ais_assignment';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logging interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerInterface            $logger
  )
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
  }


  private function extract_target_ids(array $my_array)
  {
    $return_array = [];
    foreach ($my_array as $value) {
      if (isset($value['target_id'])) {
        $return_array[] = $value['target_id'];
      }
    }
    return $return_array;
  }

  public function createAssignmentsForStudy(NodeInterface $study, $reviewers, $reviewers_per_document) {
    $all_reviewer_ids = array_map(function($u) {
      return $u->id();
    }, $reviewers);

    $documentIds = $this->getDocumentIdsInStudy($study);
    foreach ($documentIds as $documentId) {
      $assignments = $this->getAssignmentsForDocumentId($documentId);

      while (count($assignments) < $reviewers_per_document) {
        $existing_reviewers = array_map(function ($a) {
          return $a->get(self::ASSIGNMENT_USER_FIELD)->getValue()[0]['target_id'];
        }, $assignments);
        $eligible_reviewers = array_diff($all_reviewer_ids, $existing_reviewers);
        if (count($eligible_reviewers) < 1) {
          $this->logger->error("No reviewer could be assigned for document {$documentId}.");

          return NULL;
        }
        $lucky_index = array_rand($eligible_reviewers);
        $new_assignment = $this->createAssignment($documentId, $eligible_reviewers[$lucky_index]);
        if (!$new_assignment) {
          $this->logger->error("Assignments could not be completed for document {$documentId}.");
          return NULL;
        }
        $assignments = $this->getAssignmentsForDocumentId($documentId);
      }
    }
    return True;
  }

  public function getAssignmentsForDocumentId($documentId) {
    $assignment_ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', self::ASSIGNMENT_BUNDLE)
      ->condition(self::ASSIGNMENT_DOCUMENT_FIELD, $documentId)
      ->execute();
    return $this->entityTypeManager->getStorage('node')->loadMultiple($assignment_ids);

  }
  public function createAssignment(int $documentId, int $userId)
  {
    $document = $this->entityTypeManager->getStorage('node')->load($documentId);
    $user = $this->entityTypeManager->getStorage('user')->load($userId);
    // Test if document is a document
    if ($document->bundle() != self::DOCUMENT_BUNDLE) {
      $this->logger->error("Assignment requires a 'document' node, '" . $document->bundle() . "' given.");
      return NULL;
    }
    // Test if user is a member of the document's study.
    $studies = $document->get(self::DOCUMENT_STUDY_FIELD)->getValue();
    $study_id = $studies[0]['target_id'];
    $study = $this->entityTypeManager->getStorage('node')->load($study_id);
    $users_in_study = $this->extract_target_ids($study->get(self::STUDY_REVIEWERS_FIELD)->getValue());
    if (!in_array($user->id(), $users_in_study)) {
      $this->logger->error("User " . $user->getAccountName() . " must be a member of the document's study.");
      return NULL;
    }
    // Test if assignment of document to user already exists.
    if ($this->assignment_exists($document, $user)) {
      $this->logger->error("Can't create duplicate assignment of @document to @user.", [
        '@document' => $document->id(),
        '@user' => $user->getAccountName()
      ]);
      return NULL;
    }

    // Create assignment.
    $assignment = Node::create([
      'type' => self::ASSIGNMENT_BUNDLE,
      'title' => 'Assignment of ' . $document->id() . ' to ' . $user->getAccountName()
    ]);
    $assignment->set(self::ASSIGNMENT_USER_FIELD, ['target_id' => $user->id()]);
    $assignment->set(self::ASSIGNMENT_DOCUMENT_FIELD, ['target_id' => $document->id()]);
    try {
      $assignment->save();
      return $assignment->id();
    } catch (EntityStorageException $e) {
      $this->logger->error('Could not create assignment. Error: ' . $e);
      return NULL;
    }
  }


  public function assignment_exists(NodeInterface $document, UserInterface $user)
  {
    $assignment_ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', self::ASSIGNMENT_BUNDLE)
      ->condition(self::ASSIGNMENT_DOCUMENT_FIELD, $document->id())
      ->condition(self::ASSIGNMENT_USER_FIELD, $user->id())
      ->execute();
    if (!empty($assignment_ids)) {
      return True;
    } else {
      return False;
    }
  }

  /**
   * @param \Drupal\node\NodeInterface $study
   * @return array
   */
  public function getDocumentIdsInStudy(NodeInterface $study)
  {
    $document_ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::DOCUMENT_STUDY_FIELD, $study->id())
      ->execute();
    return $document_ids;
  }
}
