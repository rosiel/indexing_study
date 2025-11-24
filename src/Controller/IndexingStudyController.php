<?php

namespace Drupal\indexing_study\Controller;

use Drupal;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Testing a controller.
 */
class IndexingStudyController extends ControllerBase {
  use MessengerTrait;

  /**
   * Access callback
   */
  public function access(AccountInterface $account, AisStudyInterface $study_node): Drupal\Core\Access\AccessResultForbidden|Drupal\Core\Access\AccessResultAllowed
  {
    $users_in_study = $study_node->getReviewers();
    foreach ($users_in_study as $user_in_study) {
      if ($account->id() == $user_in_study->id()) {
        return AccessResult::allowed();
      }
    }
    if ($account->hasPermission('administer content')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }


  /**
   * Returns the Study Summary page.
   *
   */
  public function study(AisStudyInterface $study_node): array
  {
    $build = [];
    $build['#title'] = $study_node->getTitle();

    // Build the study summary section.
    $build['study'] = [
      '#type' => 'details',
      '#title' => $this->t("Study details")
    ];
    $build['study']['study_node'] = $this->entityTypeManager()
      ->getViewBuilder('node')
      ->view($study_node, 'teaser');

    // Build the documents section.
    $add_documents_url = Url::fromRoute('entity.feeds_feed.add_form', [
      'feeds_feed_type' => 'ais_document_import',
      'study' => $study_node->id(),
      'destination' => Url::fromRoute('indexing_study.study', ['study_node' => $study_node->id()])->toString()
    ]);
    $build['documents'] = [
      '#type' => 'details',
      '#title' => $this->t("Documents (@count in study)", [
        '@count' => $study_node->getDocCount()
      ]),
      'ingest' => [
        '#type' => 'link',
        '#title' => $this->t('Import'),
        '#url' => $add_documents_url,
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
        '#access' => $add_documents_url->access(),
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
    $assignment_url = Url::fromRoute('indexing_study.assign', ['study_node' => $study_node->id()]);
    $manage_assignments_url = Url::fromRoute('view.is_assignments.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['assignments'] = [
      '#type' => 'details',
      '#title' => $this->t("Assignments (@count awaiting assignment)", [
        '@count' => $needs_assignment
      ]),
    ];
    if ($needs_assignment > 0) {
      if ($assignment_url->access()) {
        $build['assignments']['assign'] = [
          '#type' => 'link',
          '#title' => $this->t('Assign'),
          '#url' => $assignment_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['assignments']['assign'] = $this->disabledButton($this->t('Assign'), $this->t('You do not have permission to assign documents.'));
      }
    }
    else {
      $build['assignments']['assign'] = $this->disabledButton($this->t('Assign'), $this->t('There are no documents to assign.'));
    }
    $build['assignments']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage assignments'),
      '#url' => $manage_assignments_url,
      '#access' => $manage_assignments_url->access()
    ];

    // Build section for analysis.
    $needs_analysis = $study_node->getAssignmentCountForAnalysis();
    $analysis_url = Url::fromRoute('indexing_study.analyze', ['study_node' => $study_node->id()]);
    $manage_analyses_url = Url::fromRoute('view.is_reviews.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['analysis'] = [
      '#type' => 'details',
      '#title' => $this->t("Analysis (@count awaiting your subject analysis)", [
        '@count' => $needs_analysis
      ]),
    ];
    if ($needs_analysis > 0) {
      if ($analysis_url->access()) {
        $build['analysis']['analyze'] = [
          '#type' => 'link',
          '#title' => $this->t('Analyze'),
          '#url' => $analysis_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['analysis']['analyze'] = $this->disabledButton($this->t('Analyze'), $this->t('You do not have permission to analyze documents.'));
      }
    }
    else {
      $build['analysis']['analyze'] = $this->disabledButton($this->t('Analyze'), $this->t('There are no documents to analyze.'));
    }
    $build['analysis']['progress'] = [
      '#type' => 'container',
      '#markup' => $this->t('Study progress')
    ];
    $build['analysis']['progress']['display'] = [
      '#type' => 'table',
      '#headers' => ['count','label'],
      '#rows' => [
        [count($study_node->getDocIdsByAnalysisCount('0')), $this->t('Documents with 0 analyses')],
        [count($study_node->getDocIdsByAnalysisCount('1')), $this->t('Documents with 1 analysis')],
        [count($study_node->getDocIdsByAnalysisCount('2')), $this->t('Documents with 2 analyses')],
        [count($study_node->getDocIdsByAnalysisCount('>2')), $this->t('Documents with over 2 analyses')],
        [count($study_node->getDocIdsRejected()), $this->t('Documents rejected')]

      ],
    ];
    $build['analysis']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage analyses'),
      '#url' => $manage_analyses_url,
      '#access' => $manage_analyses_url->access()
    ];

    // Build consensus section.
    $needs_consensus = $study_node->getDocCountAwaitingConsensus();
    $consensus_url = Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()]);
    $manage_consensus_url = Url::fromRoute('view.is_consensus.page_1', ['field_ais_study_target_id' => $study_node->id()]);

    $build['consensus'] = [
      '#type' => 'details',
      '#title' => $this->t("Consensus (@count awaiting consensus)", [
        '@count' => $needs_consensus
      ]),
    ];
    if ($needs_consensus > 0) {
      if ($consensus_url->access()) {
        $build['consensus']['consensus'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Consensus'),
          '#url' => $consensus_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['consensus']['consensus'] = $this->disabledButton($this->t('Create Consensus'), $this->t('You do not have permission to create consensus.'));
      }
    }
    else {
      $build['consensus']['consensus'] = $this->disabledButton($this->t('Create Consensus'), $this->t('There are no documents awaiting consensus.'));
    }
    $build['consensus']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage consensus'),
      '#url' => $manage_consensus_url,
      '#access' => $manage_consensus_url->access()
    ];

    // Build agreement section.
    $needs_agreement = $study_node->getDocCountAwaitingAgreement();
    $agreement_url = Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()]);
    $manage_agreement_url = Url::fromRoute('view.is_agreements.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['agreement'] = [
      '#type' => 'details',
      '#title' => $this->t("Agreement (@count awaiting agreement)", [
        '@count' => $needs_agreement
      ]),
    ];
    if ($needs_agreement > 0) {
      if ($agreement_url->access()) {
        $build['agreement']['agreement'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Agreement'),
          '#url' => $agreement_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['agreement']['agreement'] = $this->disabledButton($this->t('Create Agreement'), $this->t('You do not have permission to create agreement.'));
      }
    }
    else {
      $build['agreement']['agreement'] = $this->disabledButton($this->t('Create Agreement'), $this->t('There are no documents awaiting agreement.'));
    }
    $build['agreement']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage agreements'),
      '#url' => $manage_agreement_url,
      '#access' => $manage_agreement_url->access(),
    ];

    // Build results section.
    $result_count = $study_node->getDocCountCompleted();
    $results_url = Url::fromRoute('view.is_results.page_1', ['node' => $study_node->id()]);
    $build['results'] = [
      '#type' => 'details',
      '#open' => True,
      '#title' => $this->t("Results (@count completed)", [
        '@count' => $result_count
      ]),
    ];
    if ($result_count > 0) {
      if ($results_url->access()) {
        $build['results']['view'] = [
          '#type' => 'link',
          '#title' => $this->t('View Results'),
          '#url' => $results_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['results']['view'] = $this->disabledButton($this->t('View Results'), $this->t('You do not have permission to view results.'));
      }
    }
    else {
      $build['results']['view'] = $this->disabledButton($this->t('View Results'), $this->t('There are no results to view.'));
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

  protected function disabledButton( $label,  $title) {
    return [
      '#type'=> 'container',
      '#attributes' => [
        'class' => ['button', 'button-primary', 'is-disabled'],
        'role' => 'button',
        'aria-disabled' => 'true',
        'title' => $title
      ],
      'content' => [
        '#markup' => $label
      ]
    ];
  }

  /**
   * Returns the response page for the next assignment in a study.
   */
  public function analyze(AisStudyInterface $study_node) {
    $config = $this->config('indexing_study.settings');
    $awaiting_analysis = $study_node->getAssignmentIdsForAnalysisByUser();
    if(count($awaiting_analysis) < 1) {
      return ['#markup' => $this->t('There are no outstanding documents needing your analysis in this study. 🥳'),
        '#cache' => ['max-age' => 0]];
    }
    else {
      $assignment_id = $awaiting_analysis[array_rand($awaiting_analysis)];
      $assignment = $this->entityTypeManager()->getStorage('node')->load($assignment_id);
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
    $config = $this->config('indexing_study.settings');
    $needs_consensus = $study_node->getDocIdsAwaitingConsensus();
    if(count($needs_consensus) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing consensus in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
    }
    else {
      $document_id = $needs_consensus[array_rand($needs_consensus)];
      $document = $this->entityTypeManager()->getStorage('node')->load($document_id);
      $analyses = $document->getAnalyses();

      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('consensus.bundle')],
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
    $config = $this->config('indexing_study.settings');
    $needs_agreement = $study_node->getDocIdsAwaitingAgreement();
    if (count($needs_agreement) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing agreement in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
    } else {
      $document_id = $needs_agreement[array_rand($needs_agreement)];
      $document = $this->entityTypeManager()->getStorage('node')->load($document_id);
      $consensus_id = $document->getConsensus()[0];

      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('agreement.bundle')],
        [
          'query' => [
            'consensus' => $consensus_id,
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }
}
