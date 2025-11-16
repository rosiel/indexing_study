<?php
namespace Drupal\indexing_study\Entity;

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
  protected $config;

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
    // TODO: rewrite this to use a dtabase query.
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
      ->accessCheck(TRUE);
    if (count($completed_assignments) > 0) {
      $assignment_query->condition('nid', $completed_assignments, 'NOT IN');
    }
    return $assignment_query->execute();
  }


  // START CRUFT

  public function getDocCountInStudyAwaitingReview(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '4_needs_review');
  }
  public function getDocCountInStudyAwaitingReviewByUser(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '5_needs_review_by_user');
  }

  // END CRUFT


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

  public function getDocIdsAwaitingAssignment() {
    $config = $this->getConfig();
    // Assignment.document field shorthand
    $adf = $config->get('assignment.document_field');
    // Document study field shorthand
    $dsf = $config->get('document.study_field');
    $database = \Drupal::database();
    $query = $database->select('node','doc');
    $query->addField('doc','nid','document_id');
    $query->addExpression('COUNT(ass.nid)', 'assignment_count');
    $query->leftJoin('node__' . $adf, 'ad', 'doc.nid = ad.' . $adf . '_target_id');
    $query->leftJoin('node', 'ass', 'ass.nid=ad.entity_id AND ass.type = :asstype',
      [':asstype' => $config->get('assignment.bundle')]);
    $query->join('node__' . $dsf, 'study_field', 'study_field.entity_id = doc.nid AND study_field.' . $dsf . '_target_id = :study',
      [':study' => $this->id()]);
    $query->join('node_field_data', 'nfd1', 'doc.nid = nfd1.nid and nfd1.status = 1');
    $query->leftJoin('node_field_data', 'nfd2', 'ass.nid = nfd2.nid AND nfd2.status = 1');
    $query->condition('doc.type', $config->get('document.bundle'));
    $query->groupBy('doc.nid');
    $query->having('assignment_count < :limit', [':limit' => 2 ]);
    // add condition doc is published
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
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
    $query->leftJoin('node_field_data', 'nfd1', 'doc.nid = nfd1.nid and nfd1.status = 1');
    $query->leftJoin('node_field_data', 'nfd2', 'ass.nid = nfd2.nid AND nfd2.status = 1');
    $query->condition('doc.type', $config->get('document.bundle'));
    $query->groupBy('doc.nid');
    $query->having('assignment_count', 2, '>=');
    // add condition doc is published
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  public function getDocIdsRejected() {
    // Get all documents
    // where there exist two subject assignments that are unpublished.
    return [];
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

}
