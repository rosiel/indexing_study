<?php

namespace Drupal\indexing_study;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\indexing_study\Entity\AisStudyInterface;

class IndexingStudyUtils
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

  /**
   * Get an array of all document ids in a study.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsAll(AisStudyInterface $study): array {
    $query = $this->baseDocumentQuery($study->id());
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of all ids of documents ids in a study that need to be assigned or reassigned.
   *
   * Documents awaiting assignment are neither rejected nor fully assigned.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsAwaitingAssignment(AisStudyInterface $study): array {
    $docs_all = $this->docIdsAll($study);
    $docs_rejected = $this->docIdsRejected($study);
    $docs_fully_assigned = $this->docIdsFullyAssigned($study);
    return array_diff($docs_all, $docs_rejected, $docs_fully_assigned);
  }

  /**
   * Get array of all documents ids in a study that are rejected.
   *
   * Rejected documents have 2 or more rejected (unpublished) Assignments.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsRejected(AisStudyInterface $study): array {
    $query = $this->baseDocumentQuery($study->id());
    $adf = $this->config->get('assignment.document_field');
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

  /**
   * Get array of all document ids in a study that are fully assigned.
   *
   * Fully assigned documents have 2 or more published Assignments.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsFullyAssigned(AisStudyInterface $study): array {
    $query = $this->baseDocumentQuery($study->id());
    $adf = $this->config->get('assignment.document_field');
    $query->addExpression('COUNT(ass.nid)', 'assignment_count');
    $query->join("node__{$adf}", 'ad', "doc.nid = ad.{$adf}_target_id");
    $query->join('node', 'ass', 'ass.nid = ad.entity_id AND ass.type = :asstype',
      [':asstype' => $this->config->get('assignment.bundle')]);
    $query->join('node_field_data', 'nfd', 'ass.nid = nfd.nid AND nfd.status = 1');
    $query->groupBy('doc.nid');
    $query->having('assignment_count >= :limit', [':limit' => 2]);
    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of all document ids in a study that are awaiting subject analysis.
   *
   * Documents awaiting subject analysis have one or more associated "awaiting" Assignments.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docsIdsAwaitingAnalysis(AisStudyInterface $study): array {
    // Get all assignments that are not completed.
    $assignments = $this->assignmentIdsForAnalysis($study);
    if (!count($assignments)) {
      return [];
    }
    // Get their doc IDs.
    $adf = $this->config->get('assignment.document_field');
    $query = $this->database->select("node__{$adf}", 'assignment_doc_field');
    $query->addField('assignment_doc_field', "{$adf}_target_id", 'document_id');
    $query->condition('assignment_doc_field.entity_id', $assignments, 'IN');
    $query->orderBy('document_id');

    $results = $query->distinct()->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of document ids in a study that are awaiting consensus.
   *
   * Documents awaiting consensus have two (or more) Subject Analyses
   * but no associated Consensus.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsAwaitingConsensus(AisStudyInterface $study): array {
    // Get all documents having 2 (or more) subject analyses.
    $sadf = $this->config->get('subject_analysis.document_field');
    $query = $this->baseDocumentQuery($study->id());
    $query->addExpression('COUNT(sa.nid)', 'subject_analysis_count');
    $query->join("node__{$sadf}", 'sadf',
      "doc.nid = sadf.{$sadf}_target_id");
    $query->join('node', 'sa', 'sa.nid = sadf.entity_id AND sa.type = :sa_type', [
      ':sa_type' => $this->config->get('subject_analysis.bundle')]);
    $query->having('subject_analysis_count >= :limit', [':limit' => 2]);
    $query->groupBy('doc.nid');

    // Exclude all documents having a consensus.
    $cdf = $this->config->get('consensus.document_field');
    $subquery = $this->database->select("node__{$cdf}" ,'cdf');
    $subquery->join('node', 'con', 'con.nid = cdf.entity_id AND con.type = :consensus_type',
    [':consensus_type' => $this->config->get('consensus.bundle')]);
    $subquery->addField('cdf', $cdf . '_target_id', 'document_id');
    $query->condition('doc.nid', $subquery, 'NOT IN');

    $results = $query->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of document ids in a study awaiting agreement.
   *
   * Documents awaiting agreement have "awaiting" Agreement Assignments.
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsAwaitingAgreement(AisStudyInterface $study): array {
    // Get incomplete agreement assignments.
    $agreement_assignments = $this->agreementAssignmentIdsForAgreement($study);
    if (!count($agreement_assignments)) {
      return [];
    }
    // Get their doc ids.
    $aadf = $this->config->get('agreement_assignment.document_field');
    $query = $this->database->select("node__{$aadf}", "aadf");
    $query->addField('aadf', "{$aadf}_target_id", 'document_id');
    $query->condition('aadf.entity_id', $agreement_assignments, 'IN');
    $query->orderBy('document_id');

    $results = $query->distinct()->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of document ids in a study that are awaiting conclusion.
   *
   * Document awaiting conclusion have 2 (or more) Agreements,
   * but no associated Conclusion.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsAwaitingConclusion(AisStudyInterface $study): array {
    // Get all documents having 2 (or more) agreements.
    $adf = $this->config->get('agreement.document_field');
    $query = $this->baseDocumentQuery($study->id());
    $query->addExpression('COUNT(ag.nid)', 'agreement_count');
    $query->join("node__{$adf}" , 'adf',
      "doc.nid = adf.{$adf}_target_id");
    $query->join('node', 'ag', 'ag.nid = adf.entity_id AND ag.type = :agreement_type', [
      ':agreement_type' => $this->config->get('agreement.bundle')]);
    $query->groupBy('doc.nid');
    $query->having('agreement_count >= :limit', [':limit' => 2]);

    // Exclude documents with conclusions.
    $cdf = $this->config->get('conclusion.document_field');
    $subquery = $this->database->select("node__{$cdf}",'cdf');
    $subquery->join('node', 'con', 'con.nid = cdf.entity_id AND con.type = :con_type', [
      ':con_type' => $this->config->get('conclusion.bundle')]);
    $subquery->addField('cdf', "{$cdf}_target_id");
    $query->condition('doc.nid', $subquery, 'NOT IN');

    $results = $query->distinct()->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of document ids in a study that are completed.
   *
   * Completed documents have a Conclusion.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function docIdsCompleted(AisStudyInterface $study): array {
    // Get all documents having one or more conclusions.
    $cdf = $this->config->get('conclusion.document_field');
    $query = $this->baseDocumentQuery($study->id());
    $query->join("node__{$cdf}", 'cdf', "doc.nid = cdf.{$cdf}_target_id");
    $query->join('node', 'conclusion', 'cdf.entity_id = conclusion.nid AND conclusion.type = :conclusion_type', [
      ':conclusion_type' => $this->config->get('conclusion.bundle')
    ]);
    $results = $query->distinct()->execute()->fetchAll();
    return array_column($results, 'document_id');
  }

  /**
   * Get array of Assignment IDs in a study that are awaiting analysis.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function assignmentIdsForAnalysis(AisStudyInterface $study): array {
    return $this->assignmentIds($study, True, True);
  }

  /**
   * Get an array of Assignment IDs in a Study that are awaiting analysis and assigned to a user.
   *
   * @param AisStudyInterface $study
   * @param AccountInterface $user
   * @return array
   */
  public function assignmentIdsForAnalysisByUser(AisStudyInterface $study, AccountInterface $user): array {
    return $this->assignmentIds($study, True, True, $user);
  }

  /**
   * Get an array of Assignment IDs in a study.
   *
   * If $published_only is true, rejected assignments will be excluded.
   * If $incomplete_only is true, complete (assignments with an associated Subject Analysis) will be excluded.
   * If $assigned_user is provided, only assignments to that user will be returned.
   *
   * @param AisStudyInterface $study
   * @param bool $published_only
   * @param bool $incomplete_only
   * @param AccountInterface|NULL $assigned_user
   * @return array
   */
  protected function assignmentIds(AisStudyInterface $study, bool $published_only = False, bool $incomplete_only = False, AccountInterface $assigned_user = NULL): array {
    $query = $this->baseAssignmentQuery(
      $this->config->get('assignment.bundle'),
      $this->config->get('assignment.document_field'),
      $study->id()
    );

    if ($published_only) {
      $query->join('node_field_data', 'nfd', 'assignment.nid = nfd.nid and nfd.status = 1');
    }

    if ($assigned_user) {
      $auf = $this->config->get('assignment.user_field');
      $query->join("node__{$auf}", 'user_field', "user_field.entity_id = assignment.nid AND user_field.{$auf}_target_id = :user_id",
      [':user_id' => $assigned_user->id()]);
    }

    $result =  $query->execute()->fetchAll();
    $assignments = array_column($result, 'assignment_id');
    sort($assignments);

    if ($incomplete_only) {
      // Get nids of completed assignments.
      $query = $this->baseCompletedAssignmentQuery(
        $this->config->get('assignment.bundle'),
        $this->config->get('subject_analysis.bundle'),
        $this->config->get('subject_analysis.assignment_field')
      );

      $result = $query->execute()->fetchAll();
      $completed_assignments = array_column($result, 'assignment_id');

      $assignments = array_diff($assignments, $completed_assignments);
    }
    return $assignments;
  }

  /**
   * Get an array of Consensus IDs in a study.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function consensusIds(AisStudyInterface $study): array {
    $cdf = $this->config->get('consensus.document_field');
    $query = $this->database->select('node', 'consensus');
    $query->addField('consensus', 'nid', 'consensus_id');
    $query->condition('consensus.type', $this->config->get('consensus.bundle'));
    $query->join("node__{$cdf}", 'document_field', "document_field.entity_id = consensus.nid");
    $subquery = $this->baseDocumentQuery($study->id());
    $query->condition("document_field.{$cdf}_target_id", $subquery, "IN");
    $query->orderBy('consensus_id');

    $result =  $query->execute()->fetchAll();
    return array_column($result, 'consensus_id');

  }

  /**
   * Get an array of Agreement Assignment IDs in a study that are awaiting Agreement.
   *
   * @param AisStudyInterface $study
   * @return array
   */
  public function agreementAssignmentIdsForAgreement(AisStudyInterface $study): array {
    return $this->agreementAssignmentIds($study, True);
  }

  /**
   * Get an array of Agreement Assignment IDs in a study that are awaiting Agreement and assigned to a user.
   *
   * @param AisStudyInterface $study
   * @param AccountInterface $user
   * @return array
   */
  public function agreementAssignmentIdsForAgreementByUser(AisStudyInterface $study, AccountInterface $user): array {
    return $this->agreementAssignmentIds($study, True, $user);
  }

  /**
   * Get an array of Agreement Assignment IDs in a study.
   *
   * If $incomplete_only is true, complete (assignments with an associated Agreements) will be excluded.
   * If $assigned_user is provided, only assignments to that user will be returned.
   *
   * @param AisStudyInterface $study
   * @param bool $incomplete_only
   * @param AccountInterface|NULL $assigned_user
   * @return array
   */
  public function agreementAssignmentIds(AisStudyInterface $study, bool $incomplete_only = False, AccountInterface $assigned_user = NULL): array {
    $query = $this->baseAssignmentQuery(
      $this->config->get('agreement_assignment.bundle'),
      $this->config->get('agreement_assignment.document_field'),
      $study->id()
    );

    if ($assigned_user) {
      $auf = $this->config->get('agreement_assignment.user_field');
      $query->join("node__{$auf}", 'user_field', "user_field.entity_id = assignment.nid AND user_field.{$auf}_target_id = :user_id",
        [':user_id' => $assigned_user->id()]);
    }

    $result =  $query->execute()->fetchAll();
    $assignments = array_column($result, 'assignment_id');
    sort($assignments);

    if ($incomplete_only) {
      // Get nids of completed agreement assignments.
      $query = $this->baseCompletedAssignmentQuery(
        $this->config->get('agreement_assignment.bundle'),
        $this->config->get('agreement.bundle'),
        $this->config->get('agreement.agreement_assignment_field')
      );

      $result = $query->execute()->fetchAll();
      $completed_assignments = array_column($result, 'assignment_id');

      $assignments = array_diff($assignments, $completed_assignments);
    }
    return $assignments;
  }

  /**
   * Get query to find all documents in a study.
   *
   * @param int $study_node_id
   * @return SelectInterface
   */
  protected function baseDocumentQuery(int $study_node_id): SelectInterface
  {
    $dsf = $this->config->get('document.study_field');
    $query = $this->database->select('node', 'doc');
    $query->condition('doc.type', $this->config->get('document.bundle'));
    $query->addField('doc', 'nid', 'document_id');
    $query->join("node__{$dsf}", 'study_field', "study_field.entity_id = doc.nid AND study_field.{$dsf}_target_id = :study_id",
      [':study_id' => $study_node_id]
    );
    $query->orderBy('doc.nid');
    return $query;
  }

  /**
   * Get a query to find all assignments or agreement assignments in a study.
   *
   * @param string $bundle The machine name of the bundle representing either Assignments or Agreement Assignments.
   * @param string $doc_field The field on the Assignment/Agreement Assignment bundle that points to the Document.
   * @param int $study_id The ID of the Study.
   * @return Select|SelectInterface
   */
  public function baseAssignmentQuery(string $bundle, string $doc_field, int $study_id): Select|SelectInterface
  {
    $query = $this->database->select('node', 'assignment');
    $query->addField('assignment', 'nid', 'assignment_id');
    $query->condition('assignment.type', $bundle);
    $query->join("node__{$doc_field}", 'document_field', "document_field.entity_id = assignment.nid");
    $subquery = $this->baseDocumentQuery($study_id);
    $query->condition("document_field.{$doc_field}_target_id", $subquery, 'IN');
    $query->orderBy('assignment_id');
    return $query;
  }

  /**
   * Get a query to find all completed Assignments/Agreement Assignments in the site.
   *
   * @param string $assignment_bundle The machine name of the bundle representing either Assignments or Agreement Assignments.
   * @param string $response_bundle The machine name of the bundle representing either Subject Analyses or Agreements.
   * @param string $assignment_field The field on the Assignment/Agreement Assignment bundle that points to the Document.
   * @return Select|SelectInterface
   */
  protected function baseCompletedAssignmentQuery(string $assignment_bundle, string $response_bundle, string $assignment_field): Select|SelectInterface {
    $query = $this->database->select('node', 'response');
    $query->condition('response.type', $response_bundle);
    $query->join('node__' . $assignment_field, 'assignment_field', 'response.nid = assignment_field.entity_id');
    $query->join('node', 'assignment', "assignment_field.{$assignment_field}_target_id = assignment.nid");
    $query->condition('assignment.type', $assignment_bundle);
    $query->addField('assignment', 'nid', 'assignment_id');
    return $query;
  }


}
