<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Custom field to display review count for documents.
 *
 * @ViewsFilter("assignment_count_filter")
 */
class AssignmentCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);
    $this->node_type = $this->config->get('assignment.bundle');
    $this->relating_field = $this->config->get('assignment.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
}
