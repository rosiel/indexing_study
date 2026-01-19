<?php

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;

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
    $variables['#attached']['library'][] = 'indexing_study/display';
  }

}
