<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Custom field to display consensus count for documents.
 *
 * @ViewsFilter("consensus_count_filter")
 */
class ConsensusCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $database);
    $this->node_type = $this->config->get('consensus.bundle');
    $this->relating_field = $this->config->get('consensus.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
}
