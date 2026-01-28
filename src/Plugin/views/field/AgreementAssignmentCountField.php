<?php
namespace Drupal\indexing_study\Plugin\views\field;

/**
 * Custom field to display agreement assignment count.
 *
 * @ViewsField("agreement_assignment_count_field")
 */
class AgreementAssignmentCountField extends AbstractRelatedNodeCountField {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('agreement_assignment.bundle');
    $this->relating_field = $this->config->get('agreement_assignment.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
