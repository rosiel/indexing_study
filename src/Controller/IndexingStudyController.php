<?php

namespace Drupal\indexing_study\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Drupal\indexing_study\IndexingStudyUtils;
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
  public function access(AccountInterface $account, AisStudyInterface $study_node) {
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
  public function study(AisStudyInterface $study_node) {
    $build = [];
    $build['#title'] = $study_node->getTitle();

    // Build the study summary section.
    $build['study'] = [
      '#type' => 'details',
      '#title' => $this->t("Study details")
    ];
    $build['study']['study_node'] = \Drupal::entityTypeManager()
      ->getViewBuilder('node')
      ->view($study_node, 'teaser');

    // Build the documents section.
    $build['documents'] = [
      '#type' => 'details',
      '#title' => $this->t("Documents (@count in study)", [
        '@count' => $study_node->getDocCount()
      ]),
      'ingest' => [
        '#type' => 'link',
        '#title' => $this->t('Import'),
        '#url' => Url::fromRoute('entity.feeds_feed.add_form', [
          'feeds_feed_type' => 'ais_document_import',
          'study' => $study_node->id(),
          'destination' => Url::fromRoute('indexing_study.study', ['study_node' => $study_node->id()])->toString()
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
    ];
    $manage_documents_url = Url::fromRoute('view.is_documents.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['documents']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage documents'),
      '#url' => $manage_documents_url,
      '#access' => $manage_documents_url->access()
    ];

    // Build the assignment section.
    $needs_assignment = $study_node->getDocCountAwaitingAssignment();
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
    $manage_assignments_url = Url::fromRoute('view.is_assignments.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['assignments']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage assignments'),
      '#url' => $manage_assignments_url,
      '#access' => $manage_assignments_url->access()
    ];

    // Build section for analysis.
    $needs_analysis = $study_node->getAssignmentCountForAnalysis();
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
    $manage_analyses_url = Url::fromRoute('view.is_reviews.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['analysis']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage analyses'),
      '#url' => $manage_analyses_url,
      '#access' => $manage_analyses_url->access()
    ];

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
    $manage_consensus_url = Url::fromRoute('view.is_consensus.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['consensus']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage consensus'),
      '#url' => $manage_consensus_url,
      '#access' => $manage_consensus_url->access()
    ];

    $needs_agreement = count($this->utils->getDocumentsAwaitingAgreement($study_node));
    $build['agreement'] = [
      '#type' => 'details',
      '#title' => $this->t("Agreement (@count awaiting agreement)", [
        '@count' => $needs_agreement
      ]),
    ];
    if ($needs_agreement > 0) {
      $build['agreement']['agreement'] = [
        '#type' => 'link',
        '#title' => $this->t('Create Agreement'),
        '#url' => Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    else {
      $build['agreement']['agreement'] = [
        '#type'=> 'container',
        '#attributes' => [
          'class' => ['button', 'button-primary', 'is-disabled'],
          'role' => 'button',
          'aria-disabled' => 'true',
          'title' => $this->t('There are no documents awaiting agreement.')
        ],
        'content' => [
          '#markup' => $this->t('Create Agreement')
        ]
      ];
    }
    $manage_agreement_url = Url::fromRoute('view.is_agreements.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['agreement']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage agreements'),
      '#url' => $manage_agreement_url,
      '#access' => $manage_agreement_url->access(),
    ];

    $result_count = $study_node->getDocCountCompleted();
    $build['results'] = [
      '#type' => 'details',
      '#open' => True,
      '#title' => $this->t("Results (@count completed)", [
        '@count' => $result_count
      ]),
    ];
    if ($result_count > 0) {
      $build['results']['view'] = [
        '#type' => 'link',
        '#title' => $this->t('View Results'),
        '#url' => Url::fromRoute('view.is_results.page_1', ['node' => $study_node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];
    }
    else {
      $build['results']['view'] = [
        '#type'=> 'container',
        '#attributes' => [
          'class' => ['button', 'button-primary', 'is-disabled'],
          'role' => 'button',
          'aria-disabled' => 'true',
          'title' => $this->t('There are no results to view.')
        ],
        'content' => [
          '#markup' => $this->t('View Results')
        ]
      ];
    }
    $download_results_url = Url::fromRoute('view.is_results.data_export_1', ['node' => $study_node->id()]);
    $build['results']['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download results'),
      '#url' => $download_results_url,
      '#access' => $download_results_url->access(),
    ];
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  /**
   * Returns the response page for the next assignment in a study.
   */
  public function analyze(AisStudyInterface $study_node) {
    $config = \Drupal::config('indexing_study.settings');
    $awaiting_analysis = $study_node->getAssignmentIdsForAnalysis();
    if(count($awaiting_analysis) < 1) {
      return ['#markup' => $this->t('There are no outstanding documents needing your analysis in this study. 🥳'),
        '#cache' => ['max-age'=>0]];
    }
    else {
      $assignment_id = $awaiting_analysis[array_rand($awaiting_analysis)];
      $assignment = \Drupal::entityTypeManager()->getStorage('node')->load($assignment_id);
      $document_id = $assignment->get($config->get('assignment.document_field'))->getValue()[0]['target_id'];
      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('subject_analysis.bundle')],
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
  public function consensus(AisStudyInterface $study_node) {
    $needs_consensus = $this->utils->getDocumentsAwaitingConsensus($study_node);
    if(count($needs_consensus) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing consensus in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
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

  public function agreement(AisStudyInterface $study_node) {
    $needs_agreement = $this->utils->getDocumentsAwaitingAgreement($study_node);
    if (count($needs_agreement) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing agreement in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
    } else {
      $document_id = $needs_agreement[array_rand($needs_agreement)];
      $consensus_id = $this->utils->getConsensusForDocumentId($document_id)[0];

      return $this->redirect(
        'node.add',
        ['node_type' => $this->utils::AGREEMENT_BUNDLE],
        [
          'query' => [
            'consensus' => $consensus_id,
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }
}
