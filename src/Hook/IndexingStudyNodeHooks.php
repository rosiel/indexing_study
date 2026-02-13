<?php

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\indexing_study\Entity\AisAgreementInterface;
use Drupal\indexing_study\Entity\AisConsensusInterface;
use Drupal\node\NodeInterface;
use Exception;

/**
 * Hook implementations for nodes.
 */
class IndexingStudyNodeHooks {

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(&$variables): void {
    // Attach to all nodes
    if ($variables['node'] instanceof AisConsensusInterface) {
      $variables['#attached']['library'][] = 'indexing_study/display';
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function nodeInsertOrUpdate(NodeInterface $node): void {
    if ($node instanceof AisConsensusInterface) {
      $node->generateAgreementAssignments();
    }
  }

}
