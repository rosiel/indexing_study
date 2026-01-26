<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\indexing_study\Entity\AisAgreementAssignment;
use Drupal\indexing_study\Entity\AisAssignment;
use Drupal\indexing_study\Entity\AisConsensus;
use Drupal\indexing_study\Entity\AisStudy;
use Drupal\indexing_study\Entity\AisDocument;
use Drupal\indexing_study\Entity\AisSubjectAnalysis;

class IndexingStudyEntityAlterHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    $config = \Drupal::config('indexing_study.settings');
    if (isset($bundles['node'][$config->get('study.bundle')])) {
      $bundles['node'][$config->get('study.bundle')]['class'] = AisStudy::class;
    }
    if (isset($bundles['node'][$config->get('document.bundle')])) {
      $bundles['node'][$config->get('document.bundle')]['class'] = AisDocument::class;
    }
    if (isset($bundles['node'][$config->get('subject_analysis.bundle')])) {
      $bundles['node'][$config->get('subject_analysis.bundle')]['class'] = AisSubjectAnalysis::class;
    }
    if (isset($bundles['node'][$config->get('agreement_assignment.bundle')])) {
      $bundles['node'][$config->get('agreement_assignment.bundle')]['class'] = AisAgreementAssignment::class;
    }
    if (isset($bundles['node'][$config->get('assignment.bundle')])) {
      $bundles['node'][$config->get('assignment.bundle')]['class'] = AisAssignment::class;
    }
    if (isset($bundles['node'][$config->get('consensus.bundle')])) {
      $bundles['node'][$config->get('consensus.bundle')]['class'] = AisConsensus::class;
    }
  }


}
