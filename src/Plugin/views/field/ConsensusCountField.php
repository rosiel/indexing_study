<?php
namespace Drupal\indexing_study\Plugin\views\field;

/**
 * Custom field to display consensus count.
 *
 * @ViewsField("consensus_count_field")
 */
class ConsensusCountField extends AbstractRelatedNodeCountField{

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('consensus.bundle');
    $this->relating_field = $this->config->get('consensus.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
