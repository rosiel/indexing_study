<?php

namespace Drupal\indexing_study\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\storage\Entity\StorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Testing a controller.
 */
class IndexingStudyController extends ControllerBase {

  /**
   * Returns the management page.
   */
  public function manage() {
    $build = [
      '#markup' => $this->t('Hello world.'),
    ];
    return $build;
  }

  /**
   * Returns the response page for the next assignment in a pool.
   */
  public function respond(StorageInterface $storage) {
    if (!$storage or $storage->bundle() != 'pool') {
      return ['#markup' => $this->t('You need to pass the ID of a pool.')];
    }
    // Get the assignments that already have a response
    $completed_responses = \Drupal::entityQuery('storage')
      ->condition('type', 'response')
      ->accessCheck(TRUE)
      ->execute();
    $completed_assignments = [0];
    foreach ($completed_responses as $completed_response) {
      $response_entity = \Drupal::entityTypeManager()->getStorage('storage')->load($completed_response);
      $related_assignment = $response_entity->get('field_assignment')->getValue()[0]['target_id'];
      if ($related_assignment) {
        if (!(in_array($related_assignment, $completed_assignments))) {
          $completed_assignments[] = $related_assignment;
        }
      }
    }

    // Get current user
    $current_user = \Drupal::currentUser()->id();
    // Get assignments for that user with that pool
    $assignment_query = \Drupal::entityQuery('storage')
      ->condition('type', 'assignment')
      ->condition('field_pool', $storage->id())
      ->condition('field_user', $current_user)
      ->condition('id', $completed_assignments, 'NOT IN')
      ->accessCheck(TRUE);
    $results = $assignment_query->execute();

    if(count($results) < 1) {
      return ['#markup' => $this->t('You have no outstanding assignments to do for this pool. 🥳')];
    }
    else {
      $assignment_id = array_pop($results);
//      $response = new RedirectResponse('/study/' . $assignment_id . '/respond');
//      $response->send();
      return $this->redirect(
        'entity.storage.add_form',
        ['storage_type' => 'response'],
        [
          'query' => ['edit[field_assignment][widget][0][target_id]' => $assignment_id],
          'absolute' => TRUE,
        ]
      );
    }
  }

}