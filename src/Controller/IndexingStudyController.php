<?php

namespace Drupal\indexing_study\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Messenger\MessengerTrait;


/**
 * Testing a controller.
 */
class IndexingStudyController extends ControllerBase {
  use MessengerTrait;
  /**
   * Returns the management page.
   */
  public function study(NodeInterface $node) {
    if ($node->bundle() != IndexingStudyUtils::STUDY_BUNDLE) {
      return $this->redirect('<front>');
    }
    $build = [];
    $build['#title'] = $node->getTitle();
    $build['study'] = [
      '#type' => 'details',
      '#title' => $this->t("Study details")
    ];
    $build['study']['study_node'] = \Drupal::entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, 'teaser');
    $build['documents'] = [
      '#type' => 'details',
      '#title' => $this->t("Documents (@count in study)", [
        '@count' => $node->getDocCountInStudy()
      ]),
      'ingest' => [
        '#type' => 'link',
        '#title' => $this->t('Import'),
        '#url' => Url::fromRoute('entity.feeds_feed.add_page', ['feed_type' => 'document_import']),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ]
    ];
    $needs_assignment = $node->getDocCountInStudyAwaitingAssignment();
    $build['assignments'] = [
      '#type' => 'details',
      '#title' => $this->t("Assignments (@count awaiting assignment)", [
        '@count' => $needs_assignment
      ]),
    ];
    if ($needs_assignment > 0) {
      $build['assignments']['assign'] = [
        '#type' => 'link',
        '#title' => $this->t('Assign'),
        '#url' => Url::fromRoute('indexing_study.assign', ['study_node' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    else {
      $build['assignments']['assign'] = [
        '#type'=> 'container',
        '#attributes' => [
          'class' => ['button', 'button-primary', 'is-disabled'],
          'role' => 'button',
          'aria-disabled' => 'true',
          'title' => $this->t('There are no documents to assign.')
        ],
        'content' => [
          '#markup' => $this->t('Assign')
        ]
      ];
    }

    $needs_review = $node->getDocCountInStudyAwaitingReviewByUser();
    $build['review'] = [
      '#type' => 'details',
      '#title' => $this->t("Review (@count awaiting your subject analysis)", [
        '@count' => $needs_review
      ]),
    ];


    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  /**
   * Returns the response page for the next assignment in a pool.
   */
  public function analyze(NodeInterface $study_node) {
    if (!$study_node or $study_node->bundle() != IndexingStudyUtils::STUDY_BUNDLE) {
      $this->messenger()->addError("Study node not found.");
      return $this->redirect('<front>');
    }
    // Get assignments with responses
    $reviews = \Drupal::entityQuery('node')
      ->condition('type', IndexingStudyUtils::SUBJECT_ANALYSIS_BUNDLE)
      ->accessCheck(TRUE)
      ->execute();
    $completed_assignments = [];
    foreach ($reviews as $review_id) {
      $review_node = \Drupal::entityTypeManager()->getStorage('node')->load($review_id);
      $related_assignment = $review_node->get(IndexingStudyUtils::SUBJECT_ANALYSIS_ASSIGNMENT_FIELD)->getValue()[0]['target_id'];
      if ($related_assignment) {
        if (!(in_array($related_assignment, $completed_assignments))) {
          $completed_assignments[] = $related_assignment;
        }
      }
    }

    // Get current user
    $current_user = \Drupal::currentUser()->id();
    // Get assignments for that user with that pool
    $assignment_query = \Drupal::entityQuery('node')
      ->condition('type', 'ais_assignment')
      ->condition('field_ais_document.entity:node.field_ais_study', $study_node->id())
      ->condition('field_ais_reviewer', $current_user)
      ->condition('nid', $completed_assignments, 'NOT IN')
      ->accessCheck(TRUE);
    $assignments_4u = $assignment_query->execute();
    if(count($assignments_4u) < 1) {
      return ['#markup' => $this->t('You have no outstanding assignments to do for this pool. 🥳')];
    }
    else {
      return [
        '#title' => $this->t('hi @name', ['@name' => \Drupal::currentUser()->getAccountName()]),
        '#type' => 'markup',
        '#markup' => $this->t('@count assignments to review.', [
          '@count' => count([$assignments_4u])
        ]),
        '#cachhe' => ['max-age' => 0]
      ];
    }

//      return $this->redirect(
//        'entity.storage.add_form',
//        ['storage_type' => 'response'],
//        [
//          'query' => ['edit[field_assignment][widget][0][target_id]' => $assignment_id],
//          'absolute' => TRUE,
//        ]
//      );

  }

}
