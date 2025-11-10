<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display review count.
 *
 */
class AbstractRelatedNodeCountField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Get the related node count for a document.
   */
  protected function getRelatedNodeCount($document_id, $node_type): int {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $node_type)
      ->condition('status', 1)
      ->condition(IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD, $document_id) // Replace with your actual field name
      ->accessCheck(FALSE);

    // In Drupal 11, count() returns int directly
    return $query->count()->execute();
  }
}
