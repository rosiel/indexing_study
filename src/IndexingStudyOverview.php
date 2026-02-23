<?php

namespace Drupal\indexing_study;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\indexing_study\Entity\AisStudyInterface;

class IndexingStudyOverview
{
  use StringTranslationTrait;

  /**
   * The Indexing Study Utils.
   *
   * @var IndexingStudyUtils
   */
  protected IndexingStudyUtils $utils;

  /**
   * The Entity Type Manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Indexing Study Constructor.
   *
   * @param IndexingStudyUtils $indexingStudyUtils
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param AccountProxyInterface $currentUser
   */
  public function __construct(IndexingStudyUtils $indexingStudyUtils, EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser) {
    $this->utils = $indexingStudyUtils;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  public function overview(AisStudyInterface $study_node): array {
    $build = [];
    $build['#title'] = $study_node->getTitle();

    $build['study'] = $this->buildStudySummarySection($study_node);
    $build['documents'] = $this->buildDocumentsSection($study_node);
    $build['assignments'] = $this->buildAssignmentSection($study_node);
    $build['analysis'] = $this->buildAnalysisSection($study_node);
    $build['consensus'] = $this->buildConsensusSection($study_node);
    $build['agreement'] = $this->buildAgreementSection($study_node);
    $build['conclusion'] = $this->buildConclusionSection($study_node);
    $build['results'] = $this->buildResultsSection($study_node);

    $build['#cache'] = ['max-age' => 0];
    $build['#attached']['library'][] = 'indexing_study/display';
    return $build;
  }

  /**
   * Build the Study summary section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildStudySummarySection(AisStudyInterface $study_node): array
  {
    $study_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($this->t("Study details")),
    ];
    $study_section['study_node'] = $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($study_node, 'default');
    $edit_study_url = Url::fromRoute('entity.node.edit_form', [
      'node' => $study_node->id(),
      'destination' => Url::fromRoute('<current>')->toString()]);
    $study_section['edit_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit study'),
      '#url' => $edit_study_url,
      '#access' => $edit_study_url->access(),
    ];
    return $study_section;
  }

  /**
   * Build the Documents section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildDocumentsSection(AisStudyInterface $study_node): array
  {
    $title = $this->t("Documents (@count in study)", [
      '@count' => count($this->utils->docIdsAll($study_node)),
    ]);
    $add_documents_url = Url::fromRoute('entity.feeds_feed.add_form', [
      'feeds_feed_type' => 'ais_document_import',
      'study' => $study_node->id(),
      'destination' => Url::fromRoute('indexing_study.study', ['study_node' => $study_node->id()])->toString()
    ]);

    $documents_section = [
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
    $documents_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage documents'),
      '#url' => $manage_documents_url,
      '#access' => $manage_documents_url->access()
    ];
    return $documents_section;
  }

  /**
   * Build the Assignment section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildAssignmentSection(AisStudyInterface $study_node): array
  {
    $needs_assignment = count($this->utils->docIdsAwaitingAssignment($study_node));
    $count_rejected = count($this->utils->docIdsRejected($study_node));
    $assignment_url = Url::fromRoute('indexing_study.assign', ['study_node' => $study_node->id()]);
    $manage_assignments_url = Url::fromRoute('view.is_assignments.page_1', ['field_ais_study_target_id' => $study_node->id()]);

    if ($needs_assignment > 0) {
      $title = $this->t("Assignments (@count awaiting assignment, @count_rejected rejected)", ['@count' => $needs_assignment, '@count_rejected' => $count_rejected]);
    } else {
      $title = $this->t("Assignments (All documents are assigned, @count_rejected rejected)", ['@count_rejected' => $count_rejected]);
    }
    $assignment_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_assignment > 0) {
      if ($assignment_url->access()) {
        $assignment_section['assign'] = [
          '#type' => 'link',
          '#title' => $this->t('Assign'),
          '#url' => $assignment_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      } else {
        $assignment_section['assign'] = $this->disabledButton($this->t('Assign'), $this->t('You do not have permission to assign documents.'));
      }
    } else {
      $assignment_section['assign'] = $this->disabledButton($this->t('Assign'), $this->t('There are no documents to assign.'));
    }
    $assignment_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage assignments'),
      '#url' => $manage_assignments_url,
      '#access' => $manage_assignments_url->access()
    ];
    return $assignment_section;
  }

  /**
   * Build the Analysis section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildAnalysisSection(AisStudyInterface $study_node): array {
    $docs_needing_analysis = count($this->utils->docsIdsAwaitingAnalysis($study_node));
    $assignments_awaiting = count($this->utils->assignmentIdsForAnalysis($study_node));
    $needs_analysis_by_user = count($this->utils->assignmentIdsForAnalysisByUser($study_node, $this->currentUser));
    $analysis_url = Url::fromRoute('indexing_study.analyze', ['study_node' => $study_node->id()]);
    $manage_analyses_url = Url::fromRoute('view.is_reviews.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Analysis (@count_docs documents awaiting @count_awaiting subject analyses; @count_user waiting for you)", [
      '@count_user' => $needs_analysis_by_user,
      '@count_docs' => $docs_needing_analysis,
      '@count_awaiting' => $assignments_awaiting,
    ]);
    $analysis_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_analysis_by_user > 0) {
      if ($analysis_url->access()) {
        $analysis_section['analyze'] = [
          '#type' => 'link',
          '#title' => $this->t('Analyze'),
          '#url' => $analysis_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $analysis_section['analyze'] = $this->disabledButton($this->t('Analyze'), $this->t('You do not have permission to analyze documents.'));
      }
    }
    else {
      $analysis_section['analyze'] = $this->disabledButton($this->t('Analyze'), $this->t('There are no documents to analyze.'));
    }
    $analysis_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage analyses'),
      '#url' => $manage_analyses_url,
      '#access' => $manage_analyses_url->access()
    ];
//    $analysis_section['progress'] = [
//      '#type' => 'container',
//    ];
//    $analysis_section['progress']['display'] = $this->assignmentStatusTable($study_node);

    return $analysis_section;
  }

  /**
   * Build the Consensus section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildConsensusSection(AisStudyInterface $study_node): array {
    $needs_consensus = count($this->utils->docIdsAwaitingConsensus($study_node));
    $consensus_url = Url::fromRoute('indexing_study.consensus', ['study_node' => $study_node->id()]);
    $manage_consensus_url = Url::fromRoute('view.is_consensus.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Consensus (@count awaiting consensus)", [
      '@count' => $needs_consensus
    ]);
    $consensus_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_consensus > 0) {
      if ($consensus_url->access()) {
        $consensus_section['consensus'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Consensus'),
          '#url' => $consensus_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $consensus_section['consensus'] = $this->disabledButton($this->t('Create Consensus'), $this->t('You do not have permission to create consensus.'));
      }
    }
    else {
      $consensus_section['consensus'] = $this->disabledButton($this->t('Create Consensus'), $this->t('There are no documents awaiting consensus.'));
    }
    $consensus_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage consensus'),
      '#url' => $manage_consensus_url,
      '#access' => $manage_consensus_url->access()
    ];

    return $consensus_section;
  }

  /**
   * Build the Agreement section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildAgreementSection(AisStudyInterface $study_node): array {
    $agreement_assignments_awaiting = count($this->utils->agreementAssignmentIdsForAgreement($study_node));
    $docs_awaiting = count($this->utils->docIdsAwaitingAgreement($study_node));
    $needs_user = count($this->utils->agreementAssignmentIdsForAgreementByUser($study_node, $this->currentUser));
    $agreement_url = Url::fromRoute('indexing_study.agreement', ['study_node' => $study_node->id()]);
    $manage_agreement_url = Url::fromRoute('view.is_agreements.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Agreement (@count_docs documents awaiting @count_assignment agreements; @count waiting for you)", [
      '@count' => $needs_user,
      '@count_assignment' => $agreement_assignments_awaiting,
      '@count_docs' => $docs_awaiting
    ]);
    $agreement_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_user > 0) {
      if ($agreement_url->access()) {
        $agreement_section['agreement'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Agreement'),
          '#url' => $agreement_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $agreement_section['agreement'] = $this->disabledButton($this->t('Create Agreement'), $this->t('You do not have permission to create agreement.'));
      }
    }
    else {
      $agreement_section['agreement'] = $this->disabledButton($this->t('Create Agreement'), $this->t('There are no documents awaiting your agreement.'));
    }
    $agreement_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage agreements'),
      '#url' => $manage_agreement_url,
      '#access' => $manage_agreement_url->access(),
    ];
    $agreement_section['status'] = $this->agreementAssignmentStatusTable($study_node);
    return $agreement_section;
  }

  /**
   * Build the Conclusion section of the Overview.
   *
   * @param AisStudyInterface $study_node
   * @return array
   */
  public function buildConclusionSection(AisStudyInterface $study_node): array {
    $needs_conclusion = count($this->utils->docIdsAwaitingConclusion($study_node));
    $conclusion_url = Url::fromRoute('indexing_study.conclusion', ['study_node' => $study_node->id()]);
    $manage_conclusion_url = Url::fromRoute('view.is_conclusions.page_1', ['field_ais_study_target_id' => $study_node->id()]);
    $title = $this->t("Conclusion (@count awaiting conclusion)", [
      '@count' => $needs_conclusion
    ]);
    $conclusion_section = [
      '#type' => 'details',
      '#title' => $this->titleTag($title),
    ];
    if ($needs_conclusion > 0) {
      if ($conclusion_url->access()) {
        $conclusion_section['conclusion'] = [
          '#type' => 'link',
          '#title' => $this->t('Create Conclusion'),
          '#url' => $conclusion_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $conclusion_section['conclusion'] = $this->disabledButton($this->t('Create Conclusion'), $this->t('You do not have permission to create conclusion.'));
      }
    }
    else {
      $conclusion_section['conclusion'] = $this->disabledButton($this->t('Create Conclusion'), $this->t('There are no documents awaiting conclusion.'));
    }
    $conclusion_section['manage'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage conclusions'),
      '#url' => $manage_conclusion_url,
      '#access' => $manage_conclusion_url->access()
    ];

    return $conclusion_section;
  }

  public function buildResultsSection(AisStudyInterface $study_node): array {
    // Build results section.
    $result_count = count($this->utils->docIdsCompleted($study_node));
    $results_url = Url::fromRoute('view.multiagreement_results.page_1', ['node' => $study_node->id()]);
    $title = $this->t("Results (@count completed)", [
      '@count' => $result_count
    ]);
    $results_section = [
      '#type' => 'details',
      '#open' => True,
      '#title' => $this->titleTag($title),
    ];
    if ($result_count > 0) {
      if ($results_url->access()) {
        $results_section['view'] = [
          '#type' => 'link',
          '#title' => $this->t('View Results'),
          '#url' => $results_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $results_section['view'] = $this->disabledButton($this->t('View Results'), $this->t('You do not have permission to view results.'));
      }
    }
    else {
      $results_section['view'] = $this->disabledButton($this->t('View Results'), $this->t('There are no results to view.'));
    }
    $download_results_url = Url::fromRoute('view.multiagreement_results.data_export_1', ['node' => $study_node->id()]);
    $download_results_url_newlines = Url::fromRoute('view.multiagreement_results.data_export_2', ['node' => $study_node->id()]);
    $download_results_url_consensus = Url::fromRoute('view.subject_analysis_consensus.data_export_1', ['field_ais_study_target_id' => $study_node->id()]);
    $results_section['download'] = [
      '#type' => 'link',
      '#title' => $this->t('Download results (with pipes (|) separating multiple values - for computing)'),
      '#url' => $download_results_url,
      '#access' => $download_results_url->access(),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $results_section['download_newlines'] = [
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
        $results_section['view_consensus'] = [
          '#type' => 'link',
          '#title' => $this->t('View Consensus Terms'),
          '#url' => $results_url,
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
        ];
      }
      else {
        $results_section['view'] = $this->disabledButton($this->t('View Results'), $this->t('You do not have permission to view consensuses.'));
      }
    }
    else {
      $results_section['view'] = $this->disabledButton($this->t('View Results'), $this->t('There are no consensuses to view.'));
    }
    $results_section['download_consensus'] = [
      '#type' => 'link',
      '#title' => $this->t('Download Consensus Terms and their antecedents'),
      '#url' => $download_results_url_consensus,
      '#access' => $download_results_url_consensus->access(),
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    return $results_section;
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

}
