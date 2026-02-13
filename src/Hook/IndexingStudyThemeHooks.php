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
      'node__teaser' => [
        'base hook' => 'node',
        'template' => 'node--teaser',
      ],
      'paragraph__subjects' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--subjects'
      ],
      'paragraph__subjects_with_antecedents' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--subjects-with-antecedents'
      ],
      'field__field_reviewer_2_antecedents__subjects_with_antecedents' => [
        'base hook' => 'field',
        'template' => 'field--field-reviewer-antecedents--subjects-with-antecedents'
      ],
      'field__field_reviewer_1_antecedents__subjects_with_antecedents' => [
        'base hook' => 'field',
        'template' => 'field--field-reviewer-antecedents--subjects-with-antecedents'
      ],
    ];
  }

  #[Hook('theme_suggestions_field_alter')]
  function theme_suggestions_field_alter(array &$suggestions, array $variables): void
  {
    if ($variables['element']['#view_mode'] != 'full') {
      $suggestions[] = 'field__' . $variables['element']['#field_name'] . '__' . $variables['element']['#view_mode'];
    }
  }
}
