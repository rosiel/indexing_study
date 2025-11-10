<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\indexing_study\Plugin\views\field\AbstractRelatedNodeCountField;

/**
 * Custom field to display assignment count.
 *
 * @ViewsField("assignment_count_field")
 */
class AssignmentCountField extends AbstractRelatedNodeCountField {

  protected $node_type = IndexingStudyUtils::ASSIGNMENT_BUNDLE;
  protected $relating_field = IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD;

  protected $root_node_type = IndexingStudyUtils::DOCUMENT_BUNDLE;


  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }


}
