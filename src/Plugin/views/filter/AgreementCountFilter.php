<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\indexing_study\Plugin\views\filter\AbstractRelatedNodeCountFilter;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display subject analysis count for documents.
 *
 * @ViewsFilter("agreement_count_filter")
 */
class AgreementCountFilter extends AbstractRelatedNodeCountFilter {

  /**
   * @inheritdoc
   */
  protected $node_type = IndexingStudyUtils::AGREEMENT_BUNDLE;

  /**
   * @inheritdoc
   */
  protected $relating_field = IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD;

  /**
   * @inheritdoc
   */
  protected $root_node_type = IndexingStudyUtils::DOCUMENT_BUNDLE;

}
