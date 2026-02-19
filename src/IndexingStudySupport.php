<?php

namespace Drupal\indexing_study;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\indexing_study\Entity\AisStudy;
use Drupal\Core\Database\Connection;
use Drupal\indexing_study\Entity\AisStudyInterface;

class IndexingStudyService
{
  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The Indexing Study Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructor for Indexing Study Stats.
   *
   * @param Connection $database
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->config = $config_factory->get('indexing_study.settings');
  }

  public function docIdsAll(AisStudyInterface $study): array {
    $query = $this->baseDocumentQuery($study->id());
    return $query->execute()->fetchAll();
  }

  public function docIdsAwaitingAssignment(AisStudyInterface $study): array {
    $docs_all = $this->docIdsAll($study);
    $docs_rejected = $this->docIdsRejected($study);
    // TODO: put the fully assigned and rejected docs here, diff them.
    return array_diff($docs_all, $docs_rejected, []);
  }

  public function docIdsRejected(AisStudyInterface $study): array {
    $adf = $this->config->get('assignment.document_field');
    $query = $this->baseDocumentQuery($study->id());
    $query->addExpression('COUNT(ass.nid)', 'assignment_count');
    $query->join("node__{$adf}", 'ad', "doc.nid = ad.{$adf}_target_id");
    $query->join('node', 'ass', 'ass.nid = ad.entity_id AND ass.type = :asstype',
      [':asstype' => $this->config->get('assignment.bundle')]);
    $query->join('node_field_data', 'nfd', 'ass.nid = nfd.nid AND nfd.status = 0');
    $query->groupBy('doc.nid');
    $query->having('assignment_count >= :limit', [':limit' => 2 ]);
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');


  }
  public function docsIdsAwaitingAnalysis(AisStudyInterface $study): array {
    // Find all assignments that are not completed. Get their doc IDs.
    $assignments = $this->assignmentIdsForAnalysis($study);
    $adf = $this->config->get('assignment.document_field');
    $query = $this->database->select("node__{$adf}", 'assignment_doc_field');
    $query->addField('assignment_doc_field', "{$adf}_target_id", 'document_id');
    $subquery = $this->baseDocumentQuery($study->id());
    $query->condition("assignment_doc_field.{$adf}_target_id", $subquery, 'IN');
    if (count($assignments)) {
      $query->condition('assignment_doc_field.entity_id', $assignments, 'IN');
    }
    $results = $query->distinct()->execute()->fetchAll();
    $doc_ids = array_column($results, 'document_id');
    sort($doc_ids);
    return $doc_ids;
  }

  protected function baseDocumentQuery(int $study_node_id): SelectInterface
  {
    $dsf = $this->config->get('document.study_field');
    $query = $this->database->select('node', 'doc');
    $query->condition('doc.type', $this->config->get('document.bundle'));
    $query->addField('doc', 'nid', 'document_id');
    $query->join("node__{$dsf}", 'study_field', "study_field.entity_id = doc.nid AND study_field.{$dsf}_target_id = :study_id",
      [':study_id' => $study_node_id]
    );
    return $query;
  }

  public function assignmentIdsForAnalysis(AisStudyInterface $study): array {
    return $this->assignmentIds($study, True, True);
  }
  protected function assignmentIds(AisStudyInterface $study, $published_only = False, $incomplete_only = False): array {
    $adf = $this->config->get('assignment.document_field');
    $query = $this->database->select('node', 'assignment');
    $query->addField('assignment', 'nid', 'assignment_id');
    $query->condition('assignment.type', $this->config->get('assignment.bundle'));
    $query->join("node__{$adf}", 'document_field', "document_field.entity_id = assignment.nid");
    $subquery = $this->baseDocumentQuery($study->id());
    $query->condition("document_field.{$adf}_target_id", $subquery, 'IN');

    if ($published_only) {
      $query->join('node_field_data', 'nfd', 'assignment.nid = nfd.nid and nfd.status = 1');
    }

    $result =  $query->execute()->fetchAll();
    $assignments = array_column($result, 'assignment_id');
    sort($assignments);

    if ($incomplete_only) {
      // Get nids of completed assignments.
      $assignment_field = $this->config->get('subject_analysis.assignment_field');
      $query = $this->database->select('node', 'sa');
      $query->condition('sa.type', $this->config->get('subject_analysis.bundle'));
      $query->join('node__' . $assignment_field, 'assignment_field', 'sa.nid = assignment_field.entity_id');
      $query->join('node', 'assignment', "assignment_field.{$assignment_field}_target_id = assignment.nid" );
      $query->condition('assignment.type', $this->config->get('assignment.bundle'));
      $query->addField('assignment', 'nid', 'assignment_id');

      $result = $query->execute()->fetchAll();
      $completed_assignments = array_column($result, 'assignment_id');
      sort($completed_assignments);

      $assignments = array_diff($assignments, $completed_assignments);
    }
    return $assignments;
  }

}
