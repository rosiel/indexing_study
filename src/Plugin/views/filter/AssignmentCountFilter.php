<?php
namespace Drupal\indexing_study\Plugin\views\filter;

/**
 * Custom field to display review count for documents.
 *
 * @ViewsFilter("assignment_count_filter")
 */
class AssignmentCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('assignment.bundle');
    $this->relating_field = $this->config->get('assignment.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
}
