<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\indexing_study\Plugin\views\filter\AbstractRelatedNodeCountFilter;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display consensus count for documents.
 *
 * @ViewsFilter("consensus_count_filter")
 */
class ConsensusCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('consensus.bundle');
    $this->relating_field = $this->config->get('consensus.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
}
