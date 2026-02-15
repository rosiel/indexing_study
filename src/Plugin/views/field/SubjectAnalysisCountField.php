<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Custom field to display subject analysis count.
 *
 * @ViewsField("subject_analysis_count_field")
 */
class SubjectAnalysisCountField extends AbstractRelatedNodeCountField {
  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);
    $this->node_type = $this->config->get('subject_analysis.bundle');
    $this->relating_field = $this->config->get('subject_analysis.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }
  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
