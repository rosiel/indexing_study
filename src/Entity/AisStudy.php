<?php
namespace Drupal\indexing_study\Entity;

use Drupal\user\Entity\User;
use Exception;

class AisStudy extends AbstractAisNode implements  AisStudyInterface {

  public function getDocCount(): int {
    return count($this->getDocIdsAll());
  }

  public function getDocCountCompleted(): int {
    return count($this->getDocIdsCompleted());
  }

  public function getDocCountAwaitingAssignment(): int {
    return count($this->getDocIdsAwaitingAssignment());
  }

  public function getDocCountFullyAssigned(): int {
    return count($this->getDocIdsFullyAssigned());
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignmentCountForAnalysis(): int {
    return count($this->getAssignmentIdsForAnalysisByUser());
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAssignmentIdsForAnalysisByUser(User $user = NULL): array {
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
    $assignment_query = \Drupal::entityQuery('node')
      ->condition('type', $this->config->get('assignment.bundle'))
      ->condition($this->config->get('assignment.document_field') . '.entity:node.' . $this->config->get('document.study_field'), $this->id())
      ->condition($this->config->get('assignment.user_field'), $user->id())
      ->condition('status', 1) // Exclude rejected assignments.
      ->accessCheck(TRUE);
    if (count($completed_assignments) > 0) {
      $assignment_query->condition('nid', $completed_assignments, 'NOT IN');
    }
    return $assignment_query->execute();
  }




  /**
   * @param \Drupal\node\NodeInterface $study
   * @return array
   */
  public function getDocIdsAll()  {
    return \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition($this->config->get('document.study_field'), $this->id())
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

  /**
   * {@inheritdoc}
   */
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

  /**
   * Get doc ids with 0 subject analyses.
   *
   * @return array
   */
  public function getDocIdsWith0Analyses() {
    return $this->getDocIdsByAnalysisCount('0');
  }

  /**
   * Get doc ids with 1 subject analyses.
   *
   * @return array
   */
  public function getDocIdsWith1Analysis() {
    return $this->getDocIdsByAnalysisCount('1');
  }

  /**
   * Get doc ids with 2 subject analyses.
   *
   * @return array
   */
  public function getDocIdsWith2Analyses() {
    return $this->getDocIdsByAnalysisCount('2');
  }

  /**
   * Get doc ids with over 2 subject analyses.
   *
   * @return array
   */
  public function getDocIdsWithOver2Analyses() {
    return $this->getDocIdsByAnalysisCount('>2');
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

  public function getDocIdsCompleted() {
    $config = $this->config();
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type',$config->get('agreement.bundle'))
      ->condition('status',1)
      ->condition($config->get('agreement.document_field') . '.entity:node.' . $config->get('document.study_field'), $this->id())
      ->execute();
  }

  public function getDocIdsAwaitingConsensus(): array
  {
    $config = $this->config();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->addExpression('COUNT(sa.nid)', 'subject_analysis_count');
    $query->join('node__' . $this->config->get('subject_analysis.document_field'), 'fadsa',
      'doc.nid = fadsa.' . $this->config->get('subject_analysis.document_field') . '_target_id');
    $query->join('node', 'sa', 'sa.nid = fadsa.entity_id AND sa.type = :satype', [
      ':satype' => $config->get('subject_analysis.bundle')]);
    $query->join('node__' . $this->config->get('document.study_field'), 'study_field',
      'study_field.entity_id = doc.nid AND study_field.' . $this->config->get('document.study_field') . '_target_id = :study_id', [
        ':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $query->having('subject_analysis_count >= :limit', [':limit' => 2]);
    $subquery = $database->select('node__' . $this->config->get('consensus.document_field'),'fadc');
    $subquery->join('node', 'con', 'con.nid = fadc.entity_id');
    $subquery->addField('fadc', $this->config->get('consensus.document_field') . '_target_id', 'document_id');
    $subquery->condition('con.type', $config->get('consensus.bundle'), '=');
    $query->condition('doc.nid', $subquery, 'NOT IN');
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getDocCountAwaitingConsensus(): int {
    return count($this->getDocIdsAwaitingConsensus());
  }
  public function getDocIdsAwaitingAgreement(): array {
    $config = $this->config();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->join('node__' . $this->config->get('consensus.document_field'), 'fadcon',
      'doc.nid = fadcon.' . $this->config->get('consensus.document_field') . '_target_id');
    $query->innerJoin('node', 'con', 'con.nid = fadcon.entity_id AND con.type = :contype', [':contype' => $config->get('consensus.bundle')]);
    $query->join('node__' . $this->config->get('document.study_field') , 'study_field',
      'study_field.entity_id = doc.nid AND study_field.' . $this->config->get('document.study_field') . '_target_id = :study_id', [':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $subquery = $database->select('node__' . $this->config->get('agreement.document_field'),'fadag');
    $subquery->join('node', 'ag', 'ag.nid = fadag.entity_id');
    $subquery->addField('fadag', $this->config->get('agreement.document_field') . '_target_id', 'document_id');
    $subquery->condition('ag.type', $config->get('agreement.bundle'), '=');
    $query->condition('doc.nid', $subquery, 'NOT IN');
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }
  public function getDocCountAwaitingAgreement(): int {
    return count($this->getDocIdsAwaitingAgreement());
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

  public function getReviewers(): array
  {
    return $this->get($this->config()->get('study.reviewers_field'))->referencedEntities();
  }

  protected function getSubjectAnalyses(): array {
      $analyses = \Drupal::entityQuery('node')
          ->condition('type', $this->config->get('subject_analysis.bundle'))
         ->condition($this->config->get('assignment.document_field') . '.entity:node.' . $this->config->get('document.study_field'), $this->id())
        ->accessCheck(TRUE)
        ->execute();
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($analyses);
  }

  public function getDependents(): array
  {
    return $this->computeDependents($this->config->get('document.bundle'),
      $this->config->get('document.study_field'));
  }
}
