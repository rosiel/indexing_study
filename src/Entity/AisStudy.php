<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Exception;

class AisStudy extends AbstractAisNode implements  AisStudyInterface {

  public function getAssignmentIdsForAnalysis(): array {
    return $this->getAssignmentIds(True, True);
  }

  public function getDocsAwaitingAnalysis(): array {
    // Find all assignments that are not completed. Get their docs.
    $assignments = $this->getAssignmentIdsForAnalysis();
    $documents = $this->getDocIdsAll();
    $database = \Drupal::database();
    $adf = $this->config()->get('assignment.document_field');
    $query = $database->select("node__{$adf}", 'assignment_doc_field');
    $query->addField('assignment_doc_field', "{$adf}_target_id", 'document_id');
    if (count($assignments)) {
      $query->condition('assignment_doc_field.entity_id', $assignments, 'IN');
    }
    if (count($documents)) {
      $query->condition("assignment_doc_field.{$adf}_target_id", $documents, 'IN');
    }
    $results = $query->distinct()->execute()->fetchAll();
    return $results;
  }

  public function getConsensuses(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $this->config()->get('consensus.bundle'))
      ->condition($this->config()->get('consensus.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->execute();
    return $storage->loadMultiple($query);
  }

  /**
   * @param UserInterface|null $user
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getAssignmentIdsForAnalysisByUser(UserInterface $user = NULL): array {
    // We will calculate assignments for analysis by:
    //  * getting this study's subject analyses;
    //  * getting the 'completed' assignment from each subject analysis;
    //  * querying for all assignments in this study for the current user that aren't in that set of completed assignments.
    $analyses = $this->getSubjectAnalyses();
    $completed_assignments = [];
    foreach ($analyses as $analysis) {
      if ($analysis instanceof AisSubjectAnalysisInterface) {
        $completed_assignment = $analysis->getAssignment();
        if (!(in_array($completed_assignment, $completed_assignments))) {
          $completed_assignments[] = $completed_assignment->id();
        }
      }
    }
    // Get current user if not passed in.
    if (!$user) {
      $user = \Drupal::currentUser();
    }
    // Get assignments for that user with that study, that aren't in the completed assignments
    $assignment_query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', $this->config()->get('assignment.bundle'))
      ->condition($this->config()->get('assignment.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->condition($this->config()->get('assignment.user_field'), $user->id())
      ->condition('status', 1) // Exclude rejected assignments.
      ->accessCheck(TRUE);
    if (count($completed_assignments) > 0) {
      $assignment_query->condition('nid', $completed_assignments, 'NOT IN');
    }
    return $assignment_query->execute();
  }


  public function getDocIdsAll(): array  {
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $this->config()->get('document.bundle'))
      ->condition($this->config()->get('document.study_field'), $this->id())
      ->execute();
  }

  public function getDocIdsRejected(): array {
    $config = $this->config();
    // Assignment.document field shorthand
    $adf = $config->get('assignment.document_field');
    // Document study field shorthand
    $dsf = $config->get('document.study_field');
    $database = \Drupal::database();
    $query = $database->select('node','doc');
    $query->addField('doc','nid','document_id');
    $query->addExpression('COUNT(ass.nid)', 'assignment_count');
    $query->Join('node__' . $adf, 'ad', 'doc.nid = ad.' . $adf . '_target_id');
    $query->Join('node', 'ass', 'ass.nid=ad.entity_id AND ass.type = :asstype',
      [':asstype' => $config->get('assignment.bundle')]);
    $query->join('node__' . $dsf, 'study_field', 'study_field.entity_id = doc.nid AND study_field.' . $dsf . '_target_id = :study',
      [':study' => $this->id()]);
    $query->join('node_field_data', 'nfd1', 'doc.nid = nfd1.nid and nfd1.status = 1');
    $query->join('node_field_data', 'nfd2', 'ass.nid = nfd2.nid AND nfd2.status = 0');
    $query->condition('doc.type', $config->get('document.bundle'));
    $query->groupBy('doc.nid');
    $query->having('assignment_count >= :limit', [':limit' => 2 ]);
    // add condition doc is published
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getDocIdsAwaitingAssignment(): array {
    $docs_rejected = $this->getDocIdsRejected();
    $docs_fully_assigned = $this->getDocIdsFullyAssigned();
    $docs_all = $this->getDocIdsAll();
    return array_diff($docs_all, $docs_rejected, $docs_fully_assigned);
  }

  public function getDocIdsFullyAssigned() {
    $config = $this->config();
    // Assignment.document field shorthand
    $adf = $config->get('assignment.document_field');
    // Document study field shorthand
    $dsf = $config->get('document.study_field');
    $database = \Drupal::database();
    $query = $database->select('node','doc');
    $query->addField('doc','nid','document_id');
    $query->addExpression('COUNT(ass.nid)', 'assignment_count');
    $query->join('node__' . $adf, 'ad', 'doc.nid = ad.' . $adf . '_target_id');
    $query->join('node', 'ass', 'ass.nid=ad.entity_id AND ass.type = :asstype',
      [':asstype' => $config->get('assignment.bundle')]);
    $query->join('node__' . $dsf, 'study_field', 'study_field.entity_id = doc.nid AND study_field.' . $dsf . '_target_id = :study',
      [':study' => $this->id()]);
    $query->join('node_field_data', 'nfd1', 'doc.nid = nfd1.nid and nfd1.status = 1');
    $query->join('node_field_data', 'nfd2', 'ass.nid = nfd2.nid AND nfd2.status = 1');
    $query->condition('doc.type', $config->get('document.bundle'));
    $query->groupBy('doc.nid');
    $query->having('assignment_count >= :limit', [':limit' => 2]);
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }


  public function getDocIdsByAnalysisCount($count = NULL): array
  {
    $config = $this->config();
    // Subject analysis document field shorthand
    $sadf = $config->get('subject_analysis.document_field');
    // Document study field shorthand
    $dsf = $config->get('document.study_field');
    $database = \Drupal::database();
    $query = $database->select('node','doc');
    $query->addField('doc','nid','document_id');
    $query->addExpression('COUNT(sa.nid)', 'analysis_count');
    $query->leftJoin('node__' . $sadf, 'sad', 'doc.nid = sad.' . $sadf . '_target_id');
    $query->leftJoin('node', 'sa', 'sa.nid=sad.entity_id AND sa.type = :satype',
      [':satype' => $config->get('subject_analysis.bundle')]);
    $query->join('node__' . $dsf, 'study_field', 'study_field.entity_id = doc.nid AND study_field.' . $dsf . '_target_id = :study',
      [':study' => $this->id()]);
    $query->leftJoin('node_field_data', 'nfd1', 'doc.nid = nfd1.nid and nfd1.status = 1');
    $query->leftJoin('node_field_data', 'nfd2', 'sa.nid = nfd2.nid AND nfd2.status = 1');
    $query->condition('doc.type', $config->get('document.bundle'));
    $query->groupBy('doc.nid');
    switch ($count) {
      case '0':
        $query->having('analysis_count = 0');
        break;
      case '1':
        $query->having('analysis_count = 1');
        break;
      case '2':
        $query->having('analysis_count = 2');
        break;
      case '>2':
        $query->having('analysis_count > 2');
        break;
      default:
        $query->having('analysis_count >= 1');
    }
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getDocIdsCompleted(): array
  {
    $config = $this->config();
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type',$config->get('conclusion.bundle'))
      ->condition('status',1)
      ->condition($config->get('conclusion.document_field') . '.entity:node.' . $config->get('document.study_field'), $this->id())
      ->execute();
  }

  public function getDocIdsAwaitingConsensus(): array
  {
    $config = $this->config();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->addExpression('COUNT(sa.nid)', 'subject_analysis_count');
    $query->join('node__' . $this->config()->get('subject_analysis.document_field'), 'fadsa',
      'doc.nid = fadsa.' . $this->config()->get('subject_analysis.document_field') . '_target_id');
    $query->join('node', 'sa', 'sa.nid = fadsa.entity_id AND sa.type = :satype', [
      ':satype' => $config->get('subject_analysis.bundle')]);
    $query->join('node__' . $this->config()->get('document.study_field'), 'study_field',
      'study_field.entity_id = doc.nid AND study_field.' . $this->config()->get('document.study_field') . '_target_id = :study_id', [
        ':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $query->having('subject_analysis_count >= :limit', [':limit' => 2]);
    $subquery = $database->select('node__' . $this->config()->get('consensus.document_field'),'fadc');
    $subquery->join('node', 'con', 'con.nid = fadc.entity_id');
    $subquery->addField('fadc', $this->config()->get('consensus.document_field') . '_target_id', 'document_id');
    $subquery->condition('con.type', $config->get('consensus.bundle'), '=');
    $query->condition('doc.nid', $subquery, 'NOT IN');
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }



  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAgreementAssignmentsForUser(UserInterface $user = NULL): array {
    // We will calculate agreement assignments needing fulfillment by:
    //  * getting this study's agreement assignments;
    //  * getting the 'completed' agreement assignments from each agreement;
    //  * querying for all agreement assignments in this study for the current user that aren't in that set of completed assignments.
    $agreements = $this->getAgreements();
    $completed_assignments = [];
    foreach ($agreements as $agreement) {
      if ($agreement instanceof AisAgreementInterface) {
        $completed_assignment = $agreement->getAgreementAssignment();
        if ($completed_assignment) {
          if (!(in_array($completed_assignment, $completed_assignments))) {
            $completed_assignments[] = $completed_assignment->id();
          }
        }
      }
    }
    // Get current user if not passed in.
    if (!$user) {
      $user = \Drupal::currentUser();
    }
    // Get assignments for that user with that study, that aren't in the completed assignments
    $storage = $this->entityTypeManager()->getStorage('node');
    $assignment_query = $storage->getQuery()
      ->condition('type', $this->config()->get('agreement_assignment.bundle'))
      ->condition($this->config()->get('agreement_assignment.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->condition($this->config()->get('agreement_assignment.user_field'), $user->id())
      ->condition('status', 1) // Exclude rejected assignments.
      ->accessCheck(TRUE);
    if (count($completed_assignments) > 0) {
      $assignment_query->condition('nid', $completed_assignments, 'NOT IN');
    }
    $assignment_ids = $assignment_query->execute();
    return $storage->loadMultiple($assignment_ids);
  }

  public function createAssignments(array $reviewers): bool
  {
    $all_reviewer_ids = array_map(function($u) {
      return $u->id();
    }, $reviewers);

    $documentIds = $this->getDocIdsAwaitingAssignment();
    foreach ($documentIds as $documentId) {
      $document = $this->entityTypeManager()->getStorage('node')->load($documentId);

      while ($document->needsAssignment()) {
        $existing_reviewers = $document->getAssignedUserIds();
        $eligible_reviewers = array_diff($all_reviewer_ids, $existing_reviewers);
        if (count($eligible_reviewers) < 1) {

          // TODO Throw an error.
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

  public function getDocIdsAwaitingConclusion(): array
  {
    $config = $this->config();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->addExpression('COUNT(ag.nid)', 'agreement_count');
    $query->join('node__' . $this->config()->get('agreement.document_field'), 'fadag',
      'doc.nid = fadag.' . $this->config()->get('agreement.document_field') . '_target_id');
    $query->join('node', 'ag', 'ag.nid = fadag.entity_id AND ag.type = :agtype', [
      ':agtype' => $config->get('agreement.bundle')]);
    $query->join('node__' . $this->config()->get('document.study_field'), 'study_field',
      'study_field.entity_id = doc.nid AND study_field.' . $this->config()->get('document.study_field') . '_target_id = :study_id', [
        ':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $query->having('agreement_count >= :limit', [':limit' => 2]);
    $subquery = $database->select('node__' . $this->config()->get('conclusion.document_field'),'fadc');
    $subquery->join('node', 'con', 'con.nid = fadc.entity_id');
    $subquery->addField('fadc', $this->config()->get('conclusion.document_field') . '_target_id', 'document_id');
    $subquery->condition('con.type', $config->get('conclusion.bundle'), '=');
    $query->condition('doc.nid', $subquery, 'NOT IN');
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getReviewers(): array {
    return $this->get($this->config()->get('study.reviewers_field'))->referencedEntities();
  }

  protected function getAssignmentIds($published_only = False, $incomplete_only = False): array {
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', $this->config()->get('assignment.bundle'))
      ->condition($this->config()->get('assignment.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->accessCheck(TRUE);
    if ($published_only) {
      $query->condition('status', 1);
    }
    $analyses =  $query->execute();
    if ($incomplete_only) {
      $assignment_field = $this->config()->get('subject_analysis.assignment_field');
      $database = \Drupal::database();
      $query = $database->select('node', 'sa');
      $query->addField('assignment', 'nid');
      $query->join('node__' . $assignment_field, 'assignment_field', 'sa.nid = assignment_field.entity_id');
      $query->join('node', 'assignment', "assignment_field.{$assignment_field}_target_id = assignment.nid" );
      $query->condition('sa.type', $this->config()->get('subject_analysis.bundle'));
      $query->condition('assignment.type', $this->config()->get('assignment.bundle'));
      $assignments_completed = $query->execute()->fetchAll();
      $analyses = array_diff($analyses, array_column($assignments_completed, 'nid'));
    }
    return $analyses;
  }

  protected function getSubjectAnalyses(): array {
      $analyses = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->condition('type', $this->config()->get('subject_analysis.bundle'))
        ->condition($this->config()->get('subject_analysis.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
        ->accessCheck(TRUE)
        ->execute();
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($analyses);
  }


  public function getAgreements(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $agreements = $storage->getQuery()
      ->condition('type', $this->config()->get('agreement.bundle'))
      ->condition($this->config()->get('agreement.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->accessCheck(TRUE)
      ->execute();
    return $storage->loadMultiple($agreements);
  }

  public function getDependents(): array
  {
    return $this->computeDependents($this->config()->get('document.bundle'),
      $this->config()->get('document.study_field'));
  }

  public function getAgreementAssignments(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $agreement_assignments = $storage->getQuery()
      ->condition('type', $this->config()->get('agreement_assignment.bundle'))
      ->condition($this->config()->get('agreement_assignment.document_field') . '.entity:node.' . $this->config()->get('document.study_field'), $this->id())
      ->accessCheck(FALSE)
      ->execute();
    return $storage->loadMultiple($agreement_assignments);
  }

  public function getAgreementAssignmentsAwaiting(): array {
    $agreement_assignments = $this->getAgreementAssignments();
    return array_filter($agreement_assignments, fn($a) => !$a->isCompleted());
  }

  public function getDocsAwaitingAgreement(): array {
    $agreement_assignments_awaiting = $this->getAgreementAssignmentsAwaiting();
    $docs = array_map(fn($a) => $a->getDocument(), $agreement_assignments_awaiting);
    return array_unique($docs, SORT_REGULAR);
  }
}

