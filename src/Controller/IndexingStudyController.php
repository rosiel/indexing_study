<?php

namespace Drupal\indexing_study\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\eca_views\Event\Access;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Testing a controller.
 */
class IndexingStudyController extends ControllerBase {
  use MessengerTrait;

  /**
   * The IndexingStudyUtils.
   *
   * @var \Drupal\indexing_study\IndexingStudyUtils
   */
  protected $utils;

  /**
   * IndexingStudyController constructor.
   *
   * @param \Drupal\indexing_study\IndexingStudyUtils $utils
   *   The IndexingStudyUtils.
   */
  public function __construct(IndexingStudyUtils $utils) {
    $this->utils = $utils;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    return new static (
      $container->get('indexing_study.utils')
    );
  }

  /**
   * Access callback
   */
  public function access(AccountInterface $account, NodeInterface $study_node) {
    if ($study_node->bundle() != $this->utils::STUDY_BUNDLE) {
      return AccessResult::forbidden();
    }
    $users_in_study = array_column($study_node->get($this->utils::STUDY_REVIEWERS_FIELD)->getValue(), 'target_id');
    if (in_array($account->id(), $users_in_study)) {
          return AccessResult::allowed();
    }
    else if ($account->hasPermission('administer content')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Returns the Study Summary page.
   */
  public function study(NodeInterface $study_node) {
    $build = [];
    $build['#title'] = $study_node->getTitle();
    $build['study'] = [
      '#type' => 'details',
      '#title' => $this->t("Study details")
    ];
    $build['study']['study_node'] = \Drupal::entityTypeManager()
      ->getViewBuilder('node')
      ->view($study_node, 'teaser');
    $build['documents'] = [
      '#type' => 'details',
      '#title' => $this->t("Documents (@count in study)", [
        '@count' => $study_node->getDocCountInStudy()
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
    $needs_assignment = $study_node->getDocCountInStudyAwaitingAssignment();
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
        '#url' => Url::fromRoute('indexing_study.assign', ['study_node' => $study_node->id()]),
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

    $needs_analysis = count($this->utils->getAssignmentIdsForAnalysis($study_node));
    $build['analysis'] = [
      '#type' => 'details',
      '#title' => $this->t("Analysis (@count awaiting your subject analysis)", [
        '@count' => $needs_analysis
      ]),
    ];
    if ($needs_analysis > 0) {
      $build['analysis']['analyze'] = [
        '#type' => 'link',
        '#title' => $this->t('Analyze'),
        '#url' => Url::fromRoute('indexing_study.analyze', ['study_node' => $study_node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    else {
      $build['analysis']['analyze'] = [
        '#type'=> 'container',
        '#attributes' => [
          'class' => ['button', 'button-primary', 'is-disabled'],
          'role' => 'button',
          'aria-disabled' => 'true',
          'title' => $this->t('There are no documents to assign.')
        ],
        'content' => [
          '#markup' => $this->t('Analyze')
        ]
      ];
    }
    $needs_consensus = count($this->utils->getDocumentsAwaitingConsensus($study_node));
    $build['consensus'] = [
      '#type' => 'details',
      '#title' => $this->t("Consensus (@count awaiting consensus)", [
        '@count' => $needs_consensus
      ]),
    ];
    if ($needs_consensus > 0) {
      $build['consensus']['consensus'] = [
        '#type' => 'link',
        '#title' => $this->t('Create Consensus'),
        '#url' => Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    else {
      $build['consensus']['consensus'] = [
        '#type'=> 'container',
        '#attributes' => [
          'class' => ['button', 'button-primary', 'is-disabled'],
          'role' => 'button',
          'aria-disabled' => 'true',
          'title' => $this->t('There are no documents awaiting consensus.')
        ],
        'content' => [
          '#markup' => $this->t('Create Consensus')
        ]
      ];
    }
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  /**
   * Returns the response page for the next assignment in a study.
   */
  public function analyze(NodeInterface $study_node) {
    $awaiting_analysis = $this->utils->getAssignmentIdsForAnalysis($study_node);
    if(count($awaiting_analysis) < 1) {
      return ['#markup' => $this->t('There are no outstanding documents needing your analysis in this study. 🥳')];
    }
    else {
      $assignment_id = $awaiting_analysis[array_rand($awaiting_analysis)];
      $assignment = \Drupal::entityTypeManager()->getStorage('node')->load($assignment_id);
      $document_id = $assignment->get($this->utils::ASSIGNMENT_DOCUMENT_FIELD)->getValue()[0]['target_id'];
      return $this->redirect(
        'node.add',
        ['node_type' => $this->utils::SUBJECT_ANALYSIS_BUNDLE],
        [
          'query' => [
            'assignment' => $assignment_id,
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.analyze', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }
  /**
   * Returns the response page for the next assignment in a study.
   */
  public function consensus(NodeInterface $study_node) {
    $needs_consensus = $this->utils->getDocumentsAwaitingConsensus($study_node);
    if(count($needs_consensus) < 1) {
      return ['#markup' => $this->t('There are no outstanding documents needing consensus in this study. 🥳')];
    }
    else {
      $document_id = $needs_consensus[array_rand($needs_consensus)];
      $analyses = $this->utils->getAnalysesForDocumentId($document_id);

      return $this->redirect(
        'node.add',
        ['node_type' => $this->utils::CONSENSUS_BUNDLE],
        [
          'query' => [
            'analyses' => Yaml::encode($analyses),
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }

}
