<?php

namespace Drupal\indexing_study\Form;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Drupal\node\NodeInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for assigning documents to reviewers.
 */
class IndexingStudyAssignmentForm extends FormBase {
  use MessengerTrait;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Indexing study config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $this->config('indexing_study.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId() {
    return 'indexing_study_assignment_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, AisStudyInterface $study_node = NULL) {
    $study_title = $study_node->getTitle();
    $reviewers_per_document_value = 2;
    $documents_to_assign = (int) $study_node->getDocCountAwaitingAssignment();

    $form['study'] = [
      '#type' => 'value',
      '#value' => $study_node,
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
      '#markup' => '<br/><br/><strong>Documents to assign:</strong> ' . (string) $documents_to_assign,
    ];
    $users_in_study = $study_node->get($this->config->get('study.reviewers_field'))->getValue();
    $user_options = [];
    foreach ($users_in_study as $user_in_study) {
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

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Error if study can't be loaded.
    if (!$form_state->getValue('study') instanceof AisStudyInterface) {
      $form_state->setErrorByName('study', $this->t('Study cannot be loaded.'));
    }
    // Error if fewer users than reviewers-per-reference (hardcoded at 2).
    if (count(array_filter($form_state->getValue('reviewers'))) < 2) {
      $form_state->setErrorByName('reviewers', $this->t('There must be at least 2 reviewers assigned.'));
    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $study = $form_state->getValue('study');

    if (!($study instanceof AisStudyInterface)) {
      return;
    }

    // Get the selected users.
    $all_users = $form_state->getValue('reviewers');
    $users = [];
    foreach ($all_users as $user_id => $value) {
      if ($value != 0) {
        $user = $this->entityTypeManager->getStorage('user')->load($user_id);
        $users[] = $user;
      }
    }

    // Attempt to make the assignments.
    try {
      $study->createAssignments($users);
    } catch (Exception $e) {
      $this->messenger()->addError($e);
      $form_state->setRedirect('indexing_study.assign', ['study_node' => $study->id()]);
      return;
    }
    $form_state->setRedirect('indexing_study.study', ['study_node' => $study->id()]);
  }

}
