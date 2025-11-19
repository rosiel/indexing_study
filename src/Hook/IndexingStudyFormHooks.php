<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

class IndexingStudyFormHooks {

  /**
   * Indexing Study config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('indexing_study.settings');
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    if ($form_id == 'node_' . $this->config->get('subject_analysis.bundle') . '_form') {

      // Don't display the meta or revision information.
      $form['meta']['author']['#access'] = False;
      $form['meta']['changed']['#access'] = False;
      $form['revision_information']['#access'] = False;

      // Move subjects to the right-hand sidebar.
      $form['field_ais_subjects']['#group'] = 'advanced';
      $form['field_ais_subjects']['#weight'] = 3;

      // Move submit to the right-hand sidebar.
      $form['sidebar_submit'] = [
        '#type' => 'container',
        '#access' => True,
        '#group' => 'advanced',
        '#weight' => 5,
      ];
      $form['sidebar_submit']['actions'] = $form['actions'];
      unset($form['actions']);
      // Attach css library to make right-hand sidebar wider.
      $form['#attached']['library'][] = 'indexing_study/indexing_study';

      // Add reject button.
      $form['sidebar_submit']['actions']['reject'] = [
        '#type' => 'submit',
        '#value' => t('Reject'),
        '#submit' => [[self::class, 'rejectAssignment']],
        '#button_type' => 'secondary',
        '#attributes' => ['class' => ['button--danger']],
        '#weight' => 100,
      ];
    }
  }

  public static function rejectAssignment(array &$form, FormStateInterface $form_state){
    $config = \Drupal::config('indexing_study.settings');
    $assignment_id = $form_state->getValue($config->get('subject_analysis.assignment_field'))[0]['target_id'];
    if ($assignment_id) {
      $assignment = \Drupal::entityTypeManager()->getStorage('node')->load($assignment_id);
      if ($assignment) {
        $assignment->setUnpublished();
        $assignment->save();
      }
    }
  }

}
