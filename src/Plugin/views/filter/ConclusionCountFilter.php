<?php
namespace Drupal\indexing_study\Plugin\views\filter;

/**
 * Custom filter using conclusion count for documents.
 *
 * @ViewsFilter("conclusion_count_filter")
 */
class ConclusionCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('conclusion.bundle');
    $this->relating_field = $this->config->get('conclusion.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

}
