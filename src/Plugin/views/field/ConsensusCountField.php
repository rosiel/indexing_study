<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\indexing_study\Plugin\views\field\AbstractRelatedNodeCountField;

/**
 * Custom field to display consensus count.
 *
 * @ViewsField("consensus_count_field")
 */
class ConsensusCountField extends AbstractRelatedNodeCountField
{

  protected $node_type = IndexingStudyUtils::CONSENSUS_BUNDLE;
  protected $relating_field = IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD;

  protected $root_node_type = IndexingStudyUtils::DOCUMENT_BUNDLE;


  public function query() {
    // Do nothing - we're computing the value in parent::render().
  }

}
