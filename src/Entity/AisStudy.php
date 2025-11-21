<?php
namespace Drupal\indexing_study\Entity;

use Exception;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\migrate\Plugin\migrate\process\ArrayBuild;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;

class AisStudy extends Node implements  AisStudyInterface {


  /**
   * The Indexing Study Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig $config
   */
  protected ImmutableConfig $config;

  private function getConfig() {
    if (!isset($this->config)) {
      $this->config = \Drupal::config('indexing_study.settings');
    }
    return $this->config;
  }
  private function count_rows_in_view($view_id, $display_id) {
    $view = \Drupal\views\Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->setArguments([$this->id()]);
    $view->setItemsPerPage(0);
    $view->execute();
    $total_rows = count($view->result);
    return $total_rows;
  }
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
    return count($this->getAssignmentIdsForAnalysis());
  }

  /**
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAssignmentIdsForAnalysis(): array {
    $config = $this->getConfig();
    // TODO: rewrite this to use a database query.
    // We will calculate those for analysis by:
    //  * getting subject analyses from entityquery
    //  * getting the "completed" assignments from the subject analyses
    //
    // Get completed assignments from existing subject analyses
    $analyses = \Drupal::entityQuery('node')
      ->condition('type', $config->get('subject_analysis.bundle'))
      ->accessCheck(TRUE)
      ->execute();
    $completed_assignments = [];
    foreach ($analyses as $analysis_id) {
      $analysis_node = \Drupal::entityTypeManager()->getStorage('node')->load($analysis_id);
      $related_assignment = $analysis_node->get($config->get('subject_analysis.assignment_field'))->getValue()[0]['target_id'];
      if ($related_assignment) {
        if (!(in_array($related_assignment, $completed_assignments))) {
          $completed_assignments[] = $related_assignment;
        }
      }
    }
    // Get current user
    $current_user = \Drupal::currentUser()->id();
    // Get assignments for that user with that study, that aren't in the completed assignments
    $assignment_query = \Drupal::entityQuery('node')
      ->condition('type', $config->get('assignment.bundle'))
      ->condition($config->get('assignment.document_field') . '.entity:node.' . $config->get('document.study_field'), $this->id())
      ->condition($config->get('assignment.user_field'), $current_user)
      ->condition('status', 1)
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
    $config = $this->getConfig();
    return \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition($config->get('document.study_field'), $this->id())
      ->execute();
  }

  public function getDocIdsRejected(): array {
    $config = $this->getConfig();
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
    $config = $this->getConfig();
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
    $config = $this->getConfig();
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
    $config = $this->getConfig();
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type',$config->get('agreement.bundle'))
      ->condition('status',1)
      ->condition($config->get('agreement.document_field') . '.entity:node.' . $config->get('document.study_field'), $this->id())
      ->execute();
  }

  public function getDocIdsAwaitingConsensus(): array
  {
    $config = $this->getConfig();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->addExpression('COUNT(sa.nid)', 'subject_analysis_count');
    $query->join('node__field_ais_document', 'fadsa', 'doc.nid = fadsa.field_ais_document_target_id');
    $query->join('node', 'sa', 'sa.nid = fadsa.entity_id AND sa.type = :satype', [':satype' => $config->get('subject_analysis.bundle')]);
    $query->join('node__field_ais_study', 'study_field', 'study_field.entity_id = doc.nid AND study_field.field_ais_study_target_id = :study_id', [':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $query->having('subject_analysis_count >= :limit', [':limit' => 2]);
    $subquery = $database->select('node__field_ais_document','fadc');
    $subquery->join('node', 'con', 'con.nid = fadc.entity_id');
    $subquery->addField('fadc', 'field_ais_document_target_id', 'document_id');
    $subquery->condition('con.type', $config->get('consensus.bundle'), '=');
    $query->condition('doc.nid', $subquery, 'NOT IN');
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getDocCountAwaitingConsensus(): int {
    return count($this->getDocIdsAwaitingConsensus());
  }
  public function getDocIdsAwaitingAgreement(): array {
    $config = $this->getConfig();
    $database = \Drupal::database();
    $query = $database->select('node', 'doc');
    $query->addField('doc', 'nid', 'document_id');
    $query->join('node__field_ais_document', 'fadcon', 'doc.nid = fadcon.field_ais_document_target_id');
    $query->innerJoin('node', 'con', 'con.nid = fadcon.entity_id AND con.type = :contype', [':contype' => $config->get('consensus.bundle')]);
    $query->join('node__field_ais_study', 'study_field', 'study_field.entity_id = doc.nid AND study_field.field_ais_study_target_id = :study_id', [':study_id' => $this->id()]);
    $query->condition('doc.type', $config->get('document.bundle'), '=' );
    $query->groupBy('doc.nid');
    $subquery = $database->select('node__field_ais_document','fadag');
    $subquery->join('node', 'ag', 'ag.nid = fadag.entity_id');
    $subquery->addField('fadag', 'field_ais_document_target_id', 'document_id');
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
        $new_assignment = \Drupal::service('indexing_study.utils')->createAssignment($documentId, $eligible_reviewers[$lucky_index]);
        if (!$new_assignment) {
          throw new Exception("Assignments could not be completed for document {$documentId}.");
        }
      }
    }
    return True;
  }
}
