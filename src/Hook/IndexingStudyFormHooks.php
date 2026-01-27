<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function PHPUnit\Framework\isNumeric;

class IndexingStudyFormHooks {
  use StringTranslationTrait;

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
    // Subject analysis bundle form.
    if ($form_id == 'node_' . $this->config->get('subject_analysis.bundle') . '_form') {
      $form['#after_build'][] = [self::class, 'showDocument'];
      $form[$this->config->get('subject_analysis.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('subject_analysis.assignment_field')]['#after_build'][] = [self::class, 'setDisabled'];

      // Don't display the meta or revision information.
      $form['meta']['author']['#access'] = False;
      $form['meta']['changed']['#access'] = False;
      $form['revision_information']['#access'] = False;

      // Move subjects to the right-hand sidebar.
      $form[$this->config->get('subject_analysis.subjects_field')]['#group'] = 'advanced';
      $form[$this->config->get('subject_analysis.subjects_field')]['#weight'] = 3;
      $form[$this->config->get('subject_analysis.subjects_field')]['#attributes']['class'][] = 'is-subjects';

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

    # Consensus form.
    else if ($form_id == 'node_' . $this->config->get('consensus.bundle') . '_form') {
      $form['#attached']['library'][] = 'indexing_study/indexing_study';
      // Populate the bonus stuff for the Consensus page.
      $form['#after_build'][] = [self::class, 'showDocument'];
      $form[$this->config->get('consensus.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('consensus.subject_analysis_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $entity = $form_state->getFormObject()->getEntity();
      $analyses = $entity->get($this->config->get('consensus.subject_analysis_field'))->getValue();
      $subjects_to_compare = [
        '#type' => 'container',
        '#attributes' => ['class' => ['ais-subjects-wrapper']],
      ];
      foreach ($analyses as $delta => $analysis) {
        if (isset($analysis['target_id'])) {
          $analysis_entity = Node::load($analysis['target_id']);
          $analysis_subjects = $analysis_entity->get($this->config->get('subject_analysis.subjects_field'))->getValue();
          $options = [];
          foreach (array_column($analysis_subjects, 'value') as $option) {
            $options[$option] = $option;
          }
          $subjects_to_compare['reviewer_' . $delta + 1] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Reviewer ' . $delta + 1),
            '#options' => $options,
          ];
        }
      }
      $form[$this->config->get('consensus.subjects_paragraph_field')]['#attributes']['class'][] = 'consensus-topic';
      array_unshift($form, $subjects_to_compare);

    # Agreement form.
    } else if ($form_id == 'node_' . $this->config->get('agreement.bundle') . '_form') {
      // Populate the bonus stuff for the Agreement page.
      $form['#after_build'][] = [self::class, 'showSubjectsForAgreement'];
      $form['#after_build'][] = [self::class, 'showDocument'];
      $form[$this->config->get('agreement.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('agreement.consensus_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('agreement.agreement_assignment_field')]['#after_build'][] = [self::class, 'setDisabled'];
    } else if ($form_id == 'feeds_feed_' . $this->config->get('feed.bundle') . '_form') {
      $form[$this->config->get('feed.study_field')]['#after_build'][] = [self::class, 'setDisabled'];
    }

  }

  public static function rejectAssignment(array &$form, FormStateInterface $form_state){
    $config = \Drupal::config('indexing_study.settings');
    $assignment_id = $form_state->getValue($config->get('subject_analysis.assignment_field'))[0]['target_id'];
    if ($assignment_id) {
      $assignment = \Drupal::entityTypeManager()->getStorage('node')->load($assignment_id);
      if ($assignment and $assignment instanceof NodeInterface) {
        $assignment->setUnpublished();
        $assignment->save();
      }
    }
  }

  public static function setDisabled($element) {
    self::setAllDisabled($element);
    return $element;
  }

  public static function setAllDisabled(&$element) {
    foreach (Element::children($element) as $key) {
      $element[$key]['#disabled'] = True;
      self::setAllDisabled($element[$key]);
    }
    if (empty($element['#input'])) {
      return;
    }
    if (!empty($element['#allow_focus'])) {
      $element['#attributes']['readonly'] = 'readonly';
    }
    else {
      $element['#attributes']['disabled'] = 'disabled';
    }
    return;
  }

  public static function showDocument($element, $form_state) {
    $config = \Drupal::config('indexing_study.settings');
    $document_field = $config->get('subject_analysis.document_field'); // TODO: Fix to vary with type
    if (isset($element[$document_field]['widget'][0]['target_id']['#default_value'])) {
      $document = $element[$document_field]['widget'][0]['target_id']['#default_value'][0];
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
      $rendered_node = $view_builder->view($document, 'document_without_subjects');
      array_unshift($element, $rendered_node);
    }
    return $element;
  }

  public static function showSubjectsForAgreement($form, $form_state) {
    $config = \Drupal::config('indexing_study.settings');
    $subjects_to_compare = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#attached' => ['library' => ['indexing_study/indexing_study']],
    ];
    if (isset($form[$config->get('agreement.document_field')]['widget'][0]['target_id']['#default_value'])) {
      $consensus = $form[$config->get('agreement.document_field')]['widget'][0]['target_id']['#default_value'][0];
      $subjects_to_compare['left'] = $consensus->get($config->get('document.subjects_field'))->view('subjects_only');
      $subjects_to_compare['left']['#weight'] = 0;
    }

    if (isset($form[$config->get('agreement.consensus_field')]['widget'][0]['target_id']['#default_value'])) {
      $consensus = $form[$config->get('agreement.consensus_field')]['widget'][0]['target_id']['#default_value'][0];
      $subjects_to_compare['right'] = $consensus->get($config->get('consensus.subjects_paragraph_field'))->view('subjects_only');
      $subjects_to_compare['right']['#weight'] = 1;

    }
    array_unshift($form, $subjects_to_compare);
    return $form;
  }

  public static function showSubjectsForConsensus($form, $form_state) {
    $config = \Drupal::config('indexing_study.settings');
    $subjects_to_compare = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#attached' => ['library' => ['indexing_study/indexing_study']],
    ];
    if (isset($form[$config->get('consensus.subject_analysis_field')]['widget'][0]['target_id']['#default_value'])) {
      $subject_analysis = $form[$config->get('consensus.subject_analysis_field')]['widget'][0]['target_id']['#default_value'][0];
      $subjects_1 = $subject_analysis->get($config->get('consensus.subjects_field'))->view('subjects_only');
      $subjects_11 = $subject_analysis->get($config->get('consensus.subjects_field'))->getValue();
      $options = [];
      foreach ($subjects_11 as $subject) {
        $options[$subject['value']] = $subject['value'];
      }

      $subjects_to_compare['left'] = $subjects_1;
    }

    if (isset($form[$config->get('consensus.subject_analysis_field')]['widget'][1]['target_id']['#default_value'])) {
      $subject_analysis = $form[$config->get('consensus.subject_analysis_field')]['widget'][1]['target_id']['#default_value'][0];
      $subjects_to_compare['right'] = $subject_analysis->get($config->get('consensus.subjects_field'))->view('subjects_only');
    }
    array_unshift($form, $subjects_to_compare);
    return $form;
  }

}
