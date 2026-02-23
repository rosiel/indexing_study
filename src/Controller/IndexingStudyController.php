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
use Drupal\indexing_study\IndexingStudyOverview;
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
   * @var IndexingStudyUtils
   */
  protected IndexingStudyUtils $utils;

  /**
   * The Entity Type Manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Indexing Study Overview.
   *
   * @var IndexingStudyOverview
   */
  protected IndexingStudyOverview $indexingStudyOverview;

  /**
   * IndexingStudyController constructor.
   *
   * @param IndexingStudyUtils $utils
   * @param IndexingStudyOverview $indexing_study_overview
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(IndexingStudyUtils $utils, IndexingStudyOverview $indexing_study_overview, EntityTypeManagerInterface $entity_type_manager) {
    $this->utils = $utils;
    $this->entityTypeManager = $entity_type_manager;
    $this->indexingStudyOverview = $indexing_study_overview;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('indexing_study.utils'),
      $container->get('indexing_study.study_overview'),
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
    // Allow users with administrator-level permissions.
    if ($account->hasPermission('administer site configuration')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Returns the Study Summary page.
   */
  public function study(AisStudyInterface $study_node): array
  {
    return $this->indexingStudyOverview->overview($study_node);
  }

  /**
   * Returns the Subject Analysis page for the next awaiting Assignment in a study.
   */
  public function analyze(AisStudyInterface $study_node) {
    $config = $this->config('indexing_study.settings');
    $awaiting_analysis = $this->utils->assignmentIdsForAnalysisByUser($study_node, $this->currentUser());
    if(count($awaiting_analysis) < 1) {
      return ['#markup' => $this->t('There are no more documents awaiting your analysis in this study. 🥳'),
        '#cache' => ['max-age' => 0]];
    }
    else {
      $assignment_id = $awaiting_analysis[array_rand($awaiting_analysis)];
      $assignment = $this->entityTypeManager->getStorage('node')->load($assignment_id);
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
   * Returns the Consensus page for the next needed Consensus in a study.
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

  /**
   * Returns the Agreement page for the next awaiting Agreeement Assignment.
   */
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
   * Returns the Conclusion page for the next document awaiting Conclusion in a study.
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
