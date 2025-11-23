<?php
namespace Drupal\indexing_study\Plugin\views\filter;

/**
 * Custom field to display subject analysis count for documents.
 *
 * @ViewsFilter("subject_analysis_count_filter")
 */
class SubjectAnalysisCountFilter extends AbstractRelatedNodeCountFilter {

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->node_type = $this->config->get('subject_analysis.bundle');
    $this->relating_field = $this->config->get('subject_analysis.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
}
