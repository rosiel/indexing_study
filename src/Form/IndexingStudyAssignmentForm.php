<?php
namespace Drupal\indexing_study\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\node\NodeInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexingStudyAssignmentForm extends FormBase {
  use MessengerTrait;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Indexing study utilities.
   *
   * @var \Drupal\indexing_study\IndexingStudyUtils
   */
  protected $utils;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, IndexingStudyUtils $utils) {
    $this->entityTypeManager = $entityTypeManager;
    $this->utils = $utils;
  }

  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('entity_type.manager'),
      $container->get('indexing_study.utils')
    );
  }

  /**
   * {@inheritdoc }
   */
  public function getFormId() {
    return 'indexing_study_assignment_form';
  }

  /**
   * {@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $study_node = NULL) {
    if (!$study_node or $study_node->bundle() != IndexingStudyUtils::STUDY_BUNDLE) {
      $this->messenger()->addError($this->t('Could not load study.'));
      return $form;
    }

    $study_title = $study_node->getTitle();
    $reviewers_per_document_value = $study_node->field_ais_reveiwers_per_document->value ?? 5;
    $documents_to_assign = (int)$study_node->getDocCountAwaitingAssignment();

    $form['study'] = [
      '#type' => 'value',
      '#value' => $study_node,
    ];
    $form['reviewers_per_document'] = [
      '#type' => 'value',
      '#title' => $this->t('Reviewers per document'),
      '#value' => $reviewers_per_document_value,
    ];
    $form['study_info_display'] = [
      '#markup' => "<strong>Study title:</strong> " . $study_title . '<br/>',
    ];
    $form['study_info_edit'] = [
      '#type' => 'link',
      '#title' => $this->t("Configure Study"),
      '#url' => $study_node->toUrl('edit-form'),
    ];
    $form['documents_to_assign'] = [
      '#markup' => '<br/><br/><strong>Documents to assign:</strong> ' . (string)$documents_to_assign,
    ];
    $users_in_study = $study_node->get('field_ais_participants')->getValue();
    $user_options = array();
    foreach($users_in_study as $user_in_study) {
      $user_id = $user_in_study['target_id'];
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);
      $user_options[$user_id] = $user->getAccountName();
    }
    $form['reviewers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select reviewers to assign.'),
      '#options' => $user_options,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    if ($documents_to_assign < 1) {
      $form['reviewers']['#disabled'] = $form['submit']['#disabled'] = TRUE;
    }
    return $form;
  }

  /**
   * {@inheritdoc }
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Error if study can't be loaded.
    if ((! $form_state->getValue('study') instanceof NodeInterface) or ($form_state->getValue('study')->bundle() != IndexingStudyUtils::STUDY_BUNDLE) ) {
      $form_state->setErrorByName('study', $this->t('Study cannot be loaded.'));
    }
    // Error if less than 1 reviewer-per-reference.
    if ($form_state->getValue('reviewers_per_document') < 1) {
      $form_state->setErrorByName('reviewers_per_document', $this->t('The reviewers per document must be greater than 1.'));
    }
    // Error if fewer users than reviewers-per-reference.
    if (count(array_filter($form_state->getValue('reviewers'))) < $form_state->getValue('reviewers_per_reference')) {
      $form_state->setErrorByName('reviewers',$this->t('There must be at least as many reviewers as reviewers per document.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc }
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $study = $form_state->getValue('study');
    $reviewers_per_document = $form_state->getValue('reviewers_per_document');
    $all_users = $form_state->getValue('reviewers');
    $users = [];
    foreach ($all_users as $user_id => $value) {
      if ($value != 0) {
        $user = $this->entityTypeManager->getStorage('user')->load($user_id);
        $users[] = $user;
      }
    }
    $assignments_created = $this->utils->createAssignmentsForStudy($study, $users, $reviewers_per_document);
    if (!$assignments_created) {
      $this->messenger()->addError($this->t('An error occurred. Check the logs for details.'));
    }
    $form_state->setRedirect('indexing_study.study', ['study_node' => $study->id() ]);
  }
}
