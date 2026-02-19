<?php

namespace Drupal\indexing_study\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\indexing_study\Entity\AisAgreementAssignmentInterface;
use Drupal\indexing_study\Entity\AisStudyInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Study Overview page controller.
 */
class IndexingStudyController extends ControllerBase {
  use MessengerTrait;

  /**
   * Indexing Study Utils.
   *
   * @var \Drupal\indexing_study\IndexingStudyUtils
   */
  protected IndexingStudyUtils $utils;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * IndexingStudyController constructor.
   *
   * @param IndexingStudyUtils $utils
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(IndexingStudyUtils $utils, EntityTypeManagerInterface $entity_type_manager) {
    $this->utils = $utils;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('indexing_study.utils'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Access callback.
   */
  public function access(AccountInterface $account, AisStudyInterface $study_node): AccessResultInterface
  {
    // Allow users who are registered in this study.
    $users_in_study = $study_node->getReviewers();
    foreach ($users_in_study as $user_in_study) {
      if ($account->id() == $user_in_study->id()) {
        return AccessResult::allowed();
      }
    }
    // Allow user with administrator-level permissions.
    if ($account->hasPermission('administer site configuration')) {
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
    $title = $this->t("Study details");
    $build['study'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    $build['study']['study_node'] = $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($study_node, 'default');
    $edit_study_url = Url::fromRoute('entity.node.edit_form', [
      'node' => $study_node->id(),
      'destination' => Url::fromRoute('<current>')->toString()]);
    $build['study']['edit_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit study'),
      '#url' => $edit_study_url,
      '#access' => $edit_study_url->access(),
    ];

    // Build the documents section.
    $add_documents_url = Url::fromRoute('entity.feeds_feed.add_form', [
      'feeds_feed_type' => 'ais_document_import',
      'study' => $study_node->id(),
      'destination' => Url::fromRoute('indexing_study.study', ['study_node' => $study_node->id()])->toString()
    ]);
    $title = $this->t("Documents (@count in study)", [
      '@count' => count($this->utils->docIdsAll($study_node)),
    ]);
    $build['documents'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
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
    $needs_assignment = count($this->utils->docIdsAwaitingAssignment($study_node));
    $count_rejected = count($this->utils->docIdsRejected($study_node));
    $assignment_url = Url::fromRoute('indexing_study.assign', ['study_node' => $study_node->id()]);
    $manage_assignments_url = Url::fromRoute('view.is_assignments.page_1', ['field_ais_study_target_id' => $study_node->id()]);

    if ($needs_assignment > 0) {
      $title = $this->t("Assignments (@count awaiting assignment, @count_rejected rejected)", ['@count' => $needs_assignment, '@count_rejected' => $count_rejected]);
    }
    else {
      $title = $this->t("Assignments (All documents are assigned, @count_rejected rejected)", ['@count_rejected' => $count_rejected]);
    }
    $build['assignments'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
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
    $docs_needing_analysis = count($this->utils->docsIdsAwaitingAnalysis($study_node));
    $assignments_awaiting = count($this->utils->assignmentIdsForAnalysis($study_node));
    $needs_analysis_by_user = count($this->utils->assignmentIdsForAnalysisByUser($study_node, $this->currentUser()));
    $analysis_url = Url::fromRoute('indexing_study.analyze', ['study_node' => $study_node->id()]);
    $manage_analyses_url = Url::fromRoute('view.is_reviews.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Analysis (@count_docs documents awaiting @count_awaiting subject analyses; @count_user waiting for you)", [
      '@count_user' => $needs_analysis_by_user,
      '@count_docs' => $docs_needing_analysis,
      '@count_awaiting' => $assignments_awaiting,
    ]);
    $build['analysis'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_analysis_by_user > 0) {
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
    $build['analysis']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage analyses'),
      '#url' => $manage_analyses_url,
      '#access' => $manage_analyses_url->access()
    ];
    $build['analysis']['progress'] = [
      '#type' => 'container',
    ];
    $build['analysis']['progress']['display'] = $this->assignmentStatusTable($study_node);


    // Build consensus section.
    $needs_consensus = count($this->utils->docIdsAwaitingConsensus($study_node));
    $consensus_url = Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()]);
    $manage_consensus_url = Url::fromRoute('view.is_consensus.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Consensus (@count awaiting consensus)", [
      '@count' => $needs_consensus
    ]);
    $build['consensus'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
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
    $agreement_assignments_awaiting = count($this->utils->agreementAssignmentIdsForAgreement($study_node));
    $docs_awaiting = count($this->utils->docIdsAwaitingAgreement($study_node));
    $needs_user = count($this->utils->agreementAssignmentIdsForAgreementByUser($study_node, $this->currentUser()));
    $agreement_url = Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()]);
    $manage_agreement_url = Url::fromRoute('view.is_agreements.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Agreement (@count_docs documents awaiting @count_assignment agreements; @count waiting for you)", [
      '@count' => $needs_user,
      '@count_assignment' => $agreement_assignments_awaiting,
      '@count_docs' => $docs_awaiting
    ]);
    $build['agreement'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_user > 0) {
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
      $build['agreement']['agreement'] = $this->disabledButton($this->t('Create Agreement'), $this->t('There are no documents awaiting your agreement.'));
    }
    $build['agreement']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage agreements'),
      '#url' => $manage_agreement_url,
      '#access' => $manage_agreement_url->access(),
    ];
    $build['agreement']['status'] = $this->agreementAssignmentStatusTable($study_node);


    // Build conclusion section.
    $needs_conclusion = count($this->utils->docIdsAwaitingConclusion($study_node));
    $conclusion_url = Url::fromRoute('indexing_study.conclusion', ['study_node' => $study_node->id()]);
    $manage_conclusion_url = Url::fromRoute('view.is_conclusions.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Conclusion (@count awaiting conclusion)", [
      '@count' => $needs_conclusion
    ]);
    $build['conclusion'] = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_conclusion > 0) {
      if ($conclusion_url->access()) {
        $build['conclusion']['conclusion'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Conclusion'),
          '#url' => $conclusion_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['conclusion']['conclusion'] = $this->disabledButton($this->t('Create Conclusion'), $this->t('You do not have permission to create conclusion.'));
      }
    }
    else {
      $build['conclusion']['conclusion'] = $this->disabledButton($this->t('Create Conclusion'), $this->t('There are no documents awaiting conclusion.'));
    }
    $build['conclusion']['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage conclusions'),
      '#url' => $manage_conclusion_url,
      '#access' => $manage_conclusion_url->access()
    ];


    // Build results section.
    $result_count = count($this->utils->docIdsCompleted($study_node));
    $results_url = Url::fromRoute('view.multiagreement_results.page_1', ['node' => $study_node->id()]);
    $title = $this->t("Results (@count completed)", [
      '@count' => $result_count
    ]);
    $build['results'] = [
      '#type' => 'details',
      '#open' => True,
      '#title' => $this->titleTag($title),
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
    $download_results_url = Url::fromRoute('view.multiagreement_results.data_export_1', ['node' => $study_node->id()]);
    $download_results_url_newlines = Url::fromRoute('view.multiagreement_results.data_export_2', ['node' => $study_node->id()]);
    $download_results_url_consensus = Url::fromRoute('view.subject_analysis_consensus.data_export_1', ['field_ais_study_target_id' => $study_node->id()]);
    $build['results']['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download results (with pipes (|) separating multiple values - for computing)'),
      '#url' => $download_results_url,
      '#access' => $download_results_url->access(),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $build['results']['download_newlines'] = [
      '#type' => 'link',
      '#title' => $this->t('Download results (with newlines separating multiple values - for reading in Excel)'),
      '#url' => $download_results_url_newlines,
      '#access' => $download_results_url_newlines->access(),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    // Build Consensus Results View/Download section
    $result_count = count($this->utils->consensusIds($study_node));
    $results_url = Url::fromRoute('view.subject_analysis_consensus.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    if ($result_count > 0) {
      if ($results_url->access()) {
        $build['results']['view_consensus'] = [
          '#type' => 'link',
          '#title' => $this->t('View Consensus Terms'),
          '#url' => $results_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $build['results']['view'] = $this->disabledButton($this->t('View Results'), $this->t('You do not have permission to view consensuses.'));
      }
    }
    else {
      $build['results']['view'] = $this->disabledButton($this->t('View Results'), $this->t('There are no consensuses to view.'));
    }
    $build['results']['download_consensus'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Consensus Terms and their antecedents'),
      '#url' => $download_results_url_consensus,
      '#access' => $download_results_url_consensus->access(),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $build['#cache'] = ['max-age' => 0];
    $build['#attached']['library'][] = 'indexing_study/display';
    return $build;
  }

  protected function disabledButton($label,  $title) {
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
  protected function titleTag($title) {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $title,
      '#attributes' => [
        'role' => 'heading',
        'aria-level' => '2',
      ],
    ];
  }
  protected function assignmentStatusTable($study_node): array {
    // Get the map of reviewers to the number of awaiting assignments.
    $users_array = [];
    foreach ($study_node->getReviewers() as $user) {
      $users_array[$user->getAccountName()] = count($this->utils->assignmentIdsForAnalysisByUser($study_node, $user));
    }
    $rows = $this->rowsOfNamesAndCounts($users_array);
    return [
      '#type' => 'table',
      '#header' => ['name' => $this->t('Name'), 'count' => $this->t('Incomplete Assignments')],
      '#rows' => $rows,
      '#attributes' => ['class' => ['assignment-table']],
      '#empty' => $this->t("There are no incomplete assignments."),
    ];
  }

  protected function agreementAssignmentStatusTable($study_node): array {
    // Get map of reviewers to the number of awaiting assignments.
    $users_array = [];
    foreach ($study_node->getReviewers() as $user) {
      $users_array[$user->getAccountName()] = count($this->utils->agreementAssignmentIdsForAgreementByUser($study_node, $user));
    }
    $rows = $this->rowsOfNamesAndCounts($users_array);
    return [
      '#type' => 'table',
      '#header' => ['name' => $this->t('Name'), 'count' => $this->t('Incomplete Agreement Assignments')],
      '#rows' => $rows,
      '#attributes' => ['class' => ['assignment-table']],
      '#empty' => $this->t("There are no incomplete agreement assignments."),
    ];
  }

  protected function rowsOfNamesAndCounts(array $users_array): array
  {
    $values = [];
    foreach ($users_array as $name => $count) {
      $values[] = [
        'name' => [
          'data' => [
            '#markup' => $name,
          ],
        ],
        'count' => [
          'data' => [
            '#markup' => $count,
          ]
        ]
      ];
    }
    return $values;
  }
  /**
   * Returns the response page for the next assignment in a study.
   */
  public function analyze(AisStudyInterface $study_node) {
    $config = $this->config('indexing_study.settings');
    $awaiting_analysis = $this->utils->assignmentIdsForAnalysisByUser($study_node, $this->currentUser());
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
    $needs_consensus = $this->utils->docIdsAwaitingConsensus($study_node);
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
      $analyses_ids = array_map(fn($a): int =>  $a->id(), $analyses);

      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('consensus.bundle')],
        [
          'query' => [
            'analyses' => Yaml::encode($analyses_ids),
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }

  public function agreement(AisStudyInterface $study_node) {
    $config = $this->config('indexing_study.settings');
    $needs_agreement = $this->utils->agreementAssignmentIdsForAgreementByUser($study_node, $this->currentUser());
    if (count($needs_agreement) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing agreement in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
    } else {
      $agreement_assignment_id = $needs_agreement[array_rand($needs_agreement)];
      $agreement_assignment = $this->entityTypeManager()->getStorage('node')->load($agreement_assignment_id);
      if (!$agreement_assignment instanceof AisAgreementAssignmentInterface) {
        throw new Exception("Entity provided was not an agreement assignment.");
      }
      $document = $agreement_assignment->getDocument();
      $consensus = $agreement_assignment->getConsensus();

      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('agreement.bundle')],
        [
          'query' => [
            'consensus' => $consensus->id(),
            'document' => $document->id(),
            'agreement_assignment' => $agreement_assignment->id(),
            'destination' => Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }

  /**
   * Returns the response page for the next assignment in a study.
   */
  public function conclusion(AisStudyInterface $study_node) {
    $config = $this->config('indexing_study.settings');
    $needs_conclusion = $this->utils->docIdsAwaitingConclusion($study_node);
    if(count($needs_conclusion) < 1) {
      return [
        '#markup' => $this->t('There are no outstanding documents needing conclusion in this study. 🥳'),
        '#cache' => ['max-age'=>0]
      ];
    }
    else {
      $document_id = $needs_conclusion[array_rand($needs_conclusion)];
      $document = $this->entityTypeManager()->getStorage('node')->load($document_id);
      $agreements = $document->getAgreements();
      $agreement_ids = array_map(fn($a): int =>  $a->id(), $agreements);

      return $this->redirect(
        'node.add',
        ['node_type' => $config->get('conclusion.bundle')],
        [
          'query' => [
            'agreements' => Yaml::encode($agreement_ids),
            'document' => $document_id,
            'destination' => Url::fromRoute('indexing_study.conclusion', ['study_node' => $study_node->id()])->toString()
          ]
        ]
      );
    }
  }
}
