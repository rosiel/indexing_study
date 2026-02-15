<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Custom field to filter on Agreement Assignment count for documents.
 *
 * @ViewsFilter("agreement_assignment_count_filter")
 */
class AgreementAssignmentCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);
    $this->node_type = $this->config->get('agreement_assignment.bundle');
    $this->relating_field = $this->config->get('agreement_assignment.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

}
