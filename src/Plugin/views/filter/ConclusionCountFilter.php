<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Custom filter using conclusion count for documents.
 *
 * @ViewsFilter("conclusion_count_filter")
 */
class ConclusionCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $database);
    $this->node_type = $this->config->get('conclusion.bundle');
    $this->relating_field = $this->config->get('conclusion.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

}
