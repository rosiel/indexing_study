<?php
namespace Drupal\indexing_study\Plugin\views\field;

/**
 * Custom field to display conclusion count.
 *
 * @ViewsField("conclusion_count_field")
 */
class ConclusionCountField extends AbstractRelatedNodeCountField {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('conclusion.bundle');
    $this->relating_field = $this->config->get('conclusion.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
