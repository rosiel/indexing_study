<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Custom field to display conclusion count.
 *
 * @ViewsField("conclusion_count_field")
 */
class ConclusionCountField extends AbstractRelatedNodeCountField {

  public function __construct($configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $entity_type_manager);
    $this->node_type = $this->config->get('conclusion.bundle');
    $this->relating_field = $this->config->get('conclusion.document_field');
    $this->root_node_type = $this->config->get('document.bundle');
  }

  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
