<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\indexing_study\Entity\AisAgreementInterface;
use Drupal\indexing_study\Entity\AisConclusionInterface;
use Drupal\indexing_study\Entity\AisDocumentInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class IndexingStudyFormHooks {
  use StringTranslationTrait;

  /**
   * Indexing Study config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;


  /**
   * Construct the indexing study form hooks.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->config = $config_factory->get('indexing_study.settings');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    if (! ($form_state->getFormObject() instanceof EntityFormInterface)) {
      return;
    }
    $entity = $form_state->getFormObject()->getEntity();
    $form['#attached']['library'][] = 'indexing_study/indexing_study';
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Subject analysis bundle form.
    if ($form_id == 'node_' . $this->config->get('subject_analysis.bundle') . '_form') {
      // Show document.
      $document = $entity->get($this->config->get('subject_analysis.document_field'))->referencedEntities()[0] ?? null;
      if ($document) {
        $this->showDocument($form, $document, $view_builder, 'document_without_subjects', '-5' );
      }
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
      // Populate the Document for the Consensus page.
      $document = $entity->get($this->config->get('consensus.document_field'))->referencedEntities()[0] ?? null;
      if ($document) {
        $this->showDocument($form, $document, $view_builder, 'document_without_subjects', '-5' );
      }
      // Populate the analyses for the Consensus page.
      $this->showSubjectsForConsensus($form, $entity);

      // Add a class to the subjects (paragraph) field
      $form[$this->config->get('consensus.subjects_paragraph_field')]['#attributes']['class'][] = 'consensus-topic';

      // Set disabled fields.
      $form[$this->config->get('consensus.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('consensus.subject_analysis_field')]['#after_build'][] = [self::class, 'setDisabled'];

      // Don't display the meta or revision information.
      $form['advanced']['#access'] = False;

    # Agreement form.
    } else if ($form_id == 'node_' . $this->config->get('agreement.bundle') . '_form') {
      // Populate the Document for the Agreement page.
      $document = $entity->get($this->config->get('agreement.document_field'))->referencedEntities()[0] ?? null;
      if ($document) {
        $this->showDocument($form, $document, $view_builder, 'document_without_subjects', '-5' );
      }

      // Populate the subjects for Agreement page.
      $this->showSubjectsForAgreement($form, $entity);

      // Set disabled fields.
      $form[$this->config->get('agreement.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('agreement.consensus_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('agreement.agreement_assignment_field')]['#after_build'][] = [self::class, 'setDisabled'];

      // Don't display the meta or revision information.
      $form['advanced']['#access'] = False;

    // Conclusion form.
    } else if ($form_id == 'node_' . $this->config->get('conclusion.bundle') . '_form') {
      // Show document and subjects.
      $document = $entity->get($this->config->get('conclusion.document_field'))->referencedEntities()[0] ?? null;
      if ($document) {
        $this->showDocument($form, $document, $view_builder, 'teaser', -5);
        $this->showSubjectsForConclusion($form, $document);
      }
      $this->showAgreementsForConclusion($form, $entity, $view_builder);

      // Set disabled fields.
      $form[$this->config->get('conclusion.document_field')]['#after_build'][] = [self::class, 'setDisabled'];
      $form[$this->config->get('conclusion.agreement_field')]['#after_build'][] = [self::class, 'setDisabled'];

      // Don't display the meta or revision information.
      $form['advanced']['#access'] = False;

    // Feeds form.
    } else if ($form_id == 'feeds_feed_' . $this->config->get('feed.bundle') . '_form') {
      $form[$this->config->get('feed.study_field')]['#after_build'][] = [self::class, 'setDisabled'];
    }
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  public static function rejectAssignment(array &$form, FormStateInterface $form_state): void
  {
    $config = \Drupal::config('indexing_study.settings');
    $assignment_id = $form_state->getValue($config->get('subject_analysis.assignment_field'))[0]['target_id'];
    if ($assignment_id) {
      $assignment = \Drupal::entityTypeManager()->getStorage('node')->load($assignment_id);
      if ($assignment and $assignment instanceof NodeInterface) {
        $assignment->setUnpublished();
        $assignment->save();
        \Drupal::messenger()->addStatus("Assignment has been rejected.");
      }
    }
  }

  /**
   * @param array $element
   * @return array
   */
  public static function setDisabled(array $element): array
  {
    self::setAllDisabled($element);
    return $element;
  }

  /**
   * @param $element
   * @return void
   */
  public static function setAllDisabled(&$element): void
  {
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

  /**
   * @param array $form
   * @param AisDocumentInterface $document
   * @param EntityViewBuilderInterface $view_builder
   * @param $view_mode
   * @param $weight
   * @return void
   */
  protected function showDocument(array &$form, AisDocumentInterface $document, EntityViewBuilderInterface $view_builder, $view_mode = 'document_without_subjects', $weight = -5): void
  {
    $rendered_node = $view_builder->view($document, $view_mode);
    $form['ais_document'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-document']],
      '#weight' => $weight,
      'document' => $rendered_node,
    ];
  }

  /**
   * @param array $form
   * @param AisAgreementInterface $agreement
   * @return void
   */
  protected function showSubjectsForAgreement(array &$form, AisAgreementInterface $agreement): void
  {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#weight' => -3,
    ];
    $document = $agreement->get($this->config->get('agreement.document_field'))->referencedEntities()[0] ?? null;
    if ($document) {
      $build['left'] = $document->get($this->config->get('document.subjects_field'))->view('subjects_only');
      $build['left']['#weight'] = 0;
    }
    $consensus = $agreement->get($this->config->get('agreement.consensus_field'))->referencedEntities()[0] ?? null;
    if ($consensus) {
      $build['right'] = $consensus->get($this->config->get('consensus.subjects_paragraph_field'))->view('subjects_only');
      $build['right']['#weight'] = 1;
    }
    $form['ais agreement comparison'] = $build;
  }

  /**
   * @param array &$form
   * @param AisDocumentInterface $document
   *
   */
  protected function showSubjectsForConclusion(array &$form, AisDocumentInterface $document)
  {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#weight' => -4,
      'left' => $document->get($this->config->get('document.subjects_field'))->view('default'),
      'right' => $document->getConsensus()->get($this->config->get('consensus.subjects_paragraph_field'))->view('teaser')
    ];
    $build['left']['#weight'] = 0;
    $build['right']['#weight'] = 1;
    $build['left']['#prefix'] = '<article>';
    $build['left']['#suffix'] = '</article>';
    $build['right']['#prefix'] = '<article>';
    $build['right']['#suffix'] = '</article>';
    $form['subjects to compare'] = $build;
  }

  private function showAgreementsForConclusion(array &$form, AisConclusionInterface $entity, EntityViewBuilderInterface $view_builder): void
  {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#weight' => -3,
    ];
    # Show agreements to compare.
    $agreements = $entity->get($this->config->get('conclusion.agreement_field'))->referencedEntities();
    foreach ($agreements as $delta => $agreement) {
      $build['reviewer_' . $delta + 1] = [
        '#type' => 'container',
        '#markup' => '<h3>Agreement ' . $delta + 1 . '</h3>',
        'node' => $view_builder->view($agreement, 'teaser'),
        '#weight' => $delta,
      ];
    }
    $form['ais agreements'] = $build;
  }

  /**
   * @param $form
   * @param $entity
 */
  public function showSubjectsForConsensus(&$form, $entity): mixed
  {
    $analyses = $entity->get($this->config->get('consensus.subject_analysis_field'))->referencedEntities();
    $subjects_to_compare = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ais-subjects-wrapper']],
      '#weight' => -3,
    ];
    foreach ($analyses as $delta => $analysis) {
      $analysis_subjects = $analysis->get($this->config->get('subject_analysis.subjects_field'))->getValue();
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
    $form['ais subjects for consensus'] = $subjects_to_compare;
    return $form;
  }


}
