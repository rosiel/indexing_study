<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Exception;

class AisStudy extends AbstractAisNode implements  AisStudyInterface {

  public function getDependents(): array
  {
    return $this->computeDependents(
      $this->config()->get('document.bundle'),
      $this->config()->get('document.study_field')
    );
  }

  public function getReviewers(): array {
    return $this->get($this->config()->get('study.reviewers_field'))->referencedEntities();
  }

  public function createAssignments(array $reviewers): bool
  {
    $all_reviewer_ids = array_map(fn($u): int => $u->id(), $reviewers);

    $documentIds = $this->utils->docIdsAwaitingAssignment($this);
    foreach ($documentIds as $documentId) {
      $document = $this->entityTypeManager()->getStorage('node')->load($documentId);
      if (!$document instanceof AisDocumentInterface) {
        throw new Exception("Node {$documentId} is not an AisDocumentInterface.");
      }
      while ($document->needsAssignment()) {
        $existing_reviewers = $document->getAssignedUserIds();
        $eligible_reviewers = array_diff($all_reviewer_ids, $existing_reviewers);
        if (count($eligible_reviewers) < 1) {
          throw new Exception("No eligible reviewers for document {$documentId}.");
        }
        $lucky_index = array_rand($eligible_reviewers);
        $new_assignment = $document->createAssignment($eligible_reviewers[$lucky_index]);
        if (!$new_assignment) {
          throw new Exception("Assignments could not be completed for document {$documentId}.");
        }
      }
    }
    return True;
  }


}

