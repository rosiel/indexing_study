<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\indexing_study\Plugin\views\filter\AbstractRelatedNodeCountFilter;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display subject analysis count for documents.
 *
 * @ViewsFilter("subject_analysis_count_filter")
 */
class SubjectAnalysisCountFilter extends AbstractRelatedNodeCountFilter {

  /**
   * @inheritdoc
   */
  protected $node_type = IndexingStudyUtils::SUBJECT_ANALYSIS_BUNDLE;

  /**
   * @inheritdoc
   */
  protected $relating_field = IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD;

  /**
   * @inheritdoc
   */
  protected $root_node_type = IndexingStudyUtils::DOCUMENT_BUNDLE;

}
