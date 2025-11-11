<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\migrate\Plugin\migrate\process\ArrayBuild;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;

class AisStudy extends Node implements  AisStudyInterface {

  private function count_rows_in_view($view_id, $display_id) {
    $view = \Drupal\views\Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->setArguments([$this->id()]);
    $view->setItemsPerPage(0);
    $view->execute();
    $total_rows = count($view->result);
    return $total_rows;
  }
  public function getDocCountInStudy(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '1_docs_in_study');
  }
  public function getDocCountInStudyAwaitingAssignment(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '2_needs_assignment');
  }
  public function getDocCountInStudyFullyAssigned(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '3_fully_assigned');
  }
  public function getDocCountInStudyAwaitingReview(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '4_needs_review');
  }
  public function getDocCountInStudyAwaitingReviewByUser(): string {
    return (string)$this->count_rows_in_view('indexing_study_node_views', '5_needs_review_by_user');
  }
  /**
   * @param \Drupal\node\NodeInterface $study
   * @return array
   */
  public function getAllDocumentIds()  {
    $document_ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition(IndexingStudyUtils::DOCUMENT_STUDY_FIELD, $this->id())
      ->execute();
    return $document_ids;
  }



}
