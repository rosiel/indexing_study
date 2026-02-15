<?php

namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\user\UserInterface;
use Exception;

class AisConsensus extends AbstractAisNode implements AisConsensusInterface
{
  public function getDependents(): array
  {
    return $this->computeDependents(
      $this->config->get('agreement_assignment.bundle'),
      $this->config->get('agreement_assignment.consensus_field'));
  }

  public function getDocument(): AisDocumentInterface {
    return $this->get($this->config()->get('consensus.document_field'))->referencedEntities()[0];
  }

  public function getStudy(): AisStudyInterface {
    return $this->getDocument()->getStudy();
  }

  protected function needs_agreement_assignment(): bool {
    $document = $this->getDocument();
    $existing_agreements = $document->getAgreements();
    if (count($existing_agreements) >= 2) {
      return FALSE;
    }

    $agreement_assignments = $document->getAgreementAssignments(); # Related published agreements objects.
    $unfinished_assignment_count = 0;
    foreach ($agreement_assignments as $assignment) {
      if (!$assignment->isCompleted()) {
        $unfinished_assignment_count++;
      }
    }
    if (count($existing_agreements) + $unfinished_assignment_count >= 2) {
      return FALSE;
    }
    return TRUE;
  }

  public function generateAgreementAssignments(): void
  {
    $attempts = 0;
    while ($this->needs_agreement_assignment()) {
      $potential_reviewers = $this->getStudy()->getReviewers();

      // Get agreement authors, and remove from potential reviewers.
      $document = $this->getDocument();
      $existing_agreements = $document->getAgreements(); # Related published agreements as objects
      foreach ($existing_agreements as $agreement) {
        if (($key = array_search($agreement->getOwner(), $potential_reviewers, TRUE)) !== false) {
          unset($potential_reviewers[$key]);
        }
      }

      // Get reviewers on agreement assignments, and remove from potential reviewers.
      $agreement_assignments = $document->getAgreementAssignments(); # Related published agreement assignments objects..
      foreach ($agreement_assignments as $assignment) {
        if (($key = array_search($assignment->getUser(), $potential_reviewers, TRUE)) !== false) {
          unset($potential_reviewers[$key]);
        }
      }
      if (count($potential_reviewers) < 1) {
        throw new Exception("No eligible reviewers for document {$document->id()}.");
      }

      // See if we can pull from "ideal reviewers" who haven't authored analyses.
      $analyses = $document->getAnalyses();
      $analysis_authors = array_map(fn($a): UserInterface => $a->getOwner(), $analyses );
      $ideal_reviewers = array_udiff($potential_reviewers, $analysis_authors, [self::class, 'compare_ids']);
      if (count($ideal_reviewers) >= 1) {
        $potential_reviewers = $ideal_reviewers;
      }

      // Select a reviewer and create an agreement assignment.
      $selected_reviewer = $potential_reviewers[array_rand($potential_reviewers)];
      $this->createAgreementAssignment($selected_reviewer);

      $attempts+= 1;
      if ($attempts > 3) {
        throw new Exception("Unable to generate Agreement assignments for document {$document->id()}.");
      }

    }

  }
  public function createAgreementAssignment(UserInterface $user): AisAgreementAssignment {
    if ($this->agreementAssignmentExists($user)) {
      throw new Exception("Assignment agreement for user {$user->getAccountName()} already exists.");
    }

    $document = $this->getDocument();
    $agreement_assignment = AisAgreementAssignment::create([
      'type' => $this->config->get('agreement_assignment.bundle'),
      'title' => 'Agreement Assignment for doc ' . $document->id() . ' to ' . $user->getAccountName(),
    ]);
    $agreement_assignment->set($this->config->get('agreement_assignment.user_field'), ['target_id' => $user->id()]);
    $agreement_assignment->set($this->config->get('agreement_assignment.document_field'), ['target_id' => $document->id()]);
    $agreement_assignment->set($this->config->get('agreement_assignment.consensus_field'), ['target_id' => $this->id()]);
    try {
      $agreement_assignment->save();
      return $agreement_assignment;
    } catch (EntityStorageException $e) {
      $this->logger->error('Could not create assignment. Error: ' . $e);
      throw $e;
    }

  }

  public function agreementAssignmentExists(UserInterface $user): bool {
    $document = $this->getDocument();
    $agreement_assignment_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $this->config->get('agreement_assignment.bundle'))
      ->condition($this->config()->get('agreement_assignment.document_field'), $document->id())
      ->condition($this->config()->get('agreement_assignment.user_field'), $user->id())
      ->condition($this->config()->get('agreement_assignment.consensus_field'), $this->id())
      ->execute();
    if (!empty($agreement_assignment_ids)) {
      return True;
    } else {
      return False;
    }
  }

}

