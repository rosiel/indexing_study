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

  protected $node_type = '';
  protected $root_node_type = '';
  protected $relating_field = '';

  protected $config;

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = \Drupal::config('indexing_study.settings');
  }
  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): \Drupal\Component\Render\MarkupInterface|int|string|\Drupal\views\Render\ViewsRenderPipelineMarkup
  {
    $entity = $this->getEntity($values);

    if (!$entity instanceof NodeInterface || $entity->bundle() !== $this->root_node_type) {
      return '0';
    }

    $count = $this->getRelatedNodeCount($entity->id());
    return $count ?: '0';
  }

  /**
   * Get the related node count for a document.
   */
  protected function getRelatedNodeCount($document_id): int {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $this->node_type)
      ->condition($this->relating_field, $document_id)
      ->accessCheck(FALSE);

    // In Drupal 11, count() returns int directly
    return $query->count()->execute();
  }
}
