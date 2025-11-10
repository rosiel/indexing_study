<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display review count.
 *
 * @ViewsField("assignment_count_field")
 */
class AssignmentCountField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // Define any custom options for your field here.
    return $options;
  }

  public function query() {
    // Do nothing - we're computing the value in render().
  }
  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);

    if (!$entity instanceof NodeInterface || $entity->bundle() !== IndexingStudyUtils::DOCUMENT_BUNDLE) {
      return '0';
    }

    $count = $this->getAssignmentCount($entity->id());
    return $count ?: '0';

  }

  /**
   * Get the review count for a document.
   */
  protected function getAssignmentCount($document_id): int {
    $query = \Drupal::entityQuery('node')
      ->condition('type', IndexingStudyUtils::ASSIGNMENT_BUNDLE)
      ->condition('status', 1)
      ->condition(IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD, $document_id) // Replace with your actual field name
      ->accessCheck(FALSE);

    // In Drupal 11, count() returns int directly
    return $query->count()->execute();
  }
}
