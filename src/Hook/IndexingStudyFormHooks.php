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

  /**
   * Construct the indexing study form hooks.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('indexing_study.settings');
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    // Set subject analysis bundle form.
    if ($form_id == 'node_' . $this->config->get('subject_analysis.bundle') . '_form') {

      $form['#after_build'][] = [self::class, 'showDocument'];
      $form[$this->config->get('subject_analysis.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('subject_analysis.assignment_field')]['#after_build'][] = [self::class, 'setDisabled'];

      // Don't display the meta or revision information.
      $form['meta']['author']['#access'] = False;
      $form['meta']['changed']['#access'] = False;
      $form['revision_information']['#access'] = False;

      // Move subjects to the right-hand sidebar.
      $form['field_ais_subjects']['#group'] = 'advanced';
      $form['field_ais_subjects']['#weight'] = 3;
      $form['field_ais_subjects']['#attributes']['class'][] = 'is-subjects';

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
        '#limit_validation_errors' => [[$this->config->get('subject_analysis.assignment_field')]],
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

  public static function setDisabled($element, $form_state) {
    // TODO: Refactor to be like ECA's FormFieldDisable.
    if (isset($element['widget'][0]['target_id']['#default_value'])) {
      $element['widget'][0]['target_id']['#attributes']['disabled'] = 'disabled';
    }
    return $element;
  }

  public static function showDocument($element, $form_state) {
    if (isset($element['field_ais_document']['widget'][0]['target_id']['#default_value'])) {
      $document = $element['field_ais_document']['widget'][0]['target_id']['#default_value'][0];
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
      array_unshift($element, $view_builder->view($document, 'document_without_subjects'));
    } ;

    return $element;
  }

}
