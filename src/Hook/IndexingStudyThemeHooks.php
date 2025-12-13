<?php
namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class IndexingStudyThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'node__document_without_subjects' => [
        'base hook' => 'node',
        'template' => 'node--document-without-subjects',
      ],
      'node__document_with_subjects' => [
        'base hook' => 'node',
        'template' => 'node--document-with-subjects',
      ],
    ];
  }

}
