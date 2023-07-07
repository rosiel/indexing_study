<?php
namespace Drupal\indexing_study\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\storage\Entity\Storage;
use Drupal\storage\Entity\StorageInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexingStudyAssignmentForm extends FormBase {

  /**
   * {@inheritdoc }
   */
  public function getFormId() {
    return 'indexing_study_assignment_form';
  }

  /**
   * {@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state, StorageInterface $storage = NULL) {
    $form['pool'] = [
      '#type' => 'value',
      '#value' => $storage,
    ];
    $form['reviewers_per_reference'] = [
      '#type' => 'number',
      '#title' => $this->t('Reviewers per reference'),
      '#default_value' => 2,
    ];
    $form['users'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select reviewers to assign.'),
      '#options' => array('alex' => 'alex', 'rosie'=>'rosie', 'lori' => 'lori'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc }
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Error if pool can't be loaded.
    if (! $form_state->getValue('pool') instanceof StorageInterface) {
      $form_state->setErrorByName('pool', $this->t('Cannot compute pool.'));
    }
    // Error if less than 1 reviewer-per-reference.
    if ($form_state->getValue('reviewers_per_reference') < 1) {
      $form_state->setErrorByName('reviewers_per_reference', $this->t('The reviewers per reference must be greater than 1.'));
    }
    // Error if fewer users than reviewers-per-reference.
    if (count(array_filter($form_state->getValue('users'))) < $form_state->getValue('reviewers_per_reference')) {
      $form_state->setErrorByName('users',$this->t('There must be at least as many users as users per reference.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc }
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $utils = \Drupal::service('indexing_study.utils');

    // Get all references in pool.
    $pool = $form_state->getValue('pool');
    $pool_references = $utils->getReferencesInPool($pool);

    // For each reference in pool
    foreach($pool_references as $reference){
      // Does it have fewer than needed?
      while ($utils->countAssignments($reference) < $form_state->getValue('reviewers_per_reference')) {
        // add reference
      }

    }

    // Who is currently assigned to review it?
    // load reviewers
    // Select (need) reviewers from reviewers not already assigned
    // Create assignments for each reviewer.
  }
}