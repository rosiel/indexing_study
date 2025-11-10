<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\indexing_study\Plugin\views\field\AbstractRelatedNodeCountField;
use Drupal\node\NodeInterface;
use Drupal\views\ResultRow;

/**
 * Custom field to display subject analysis count.
 *
 * @ViewsField("subject_analysis_count_field")
 */
class SubjectAnalysisCountField extends AbstractRelatedNodeCountField {

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

    $count = $this->getRelatedNodeCount($entity->id(), IndexingStudyUtils::SUBJECT_ANALYSIS_BUNDLE);
    return $count ?: '0';
  }

}
