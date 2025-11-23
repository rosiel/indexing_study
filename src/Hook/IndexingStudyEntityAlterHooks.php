<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\indexing_study\Entity\AisAssignment;
use Drupal\indexing_study\Entity\AisStudy;
use Drupal\indexing_study\Entity\AisDocument;
use Drupal\indexing_study\Entity\AisSubjectAnalysis;

class IndexingStudyEntityAlterHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['node']['ais_study'])) {
      $bundles['node']['ais_study']['class'] = AisStudy::class;
    }
    if (isset($bundles['node']['ais_document'])) {
      $bundles['node']['ais_document']['class'] = AisDocument::class;
    }
    if (isset($bundles['node']['ais_subject_analysis'])) {
      $bundles['node']['ais_subject_analysis']['class'] = AisSubjectAnalysis::class;
    }
    if (isset($bundles['node']['ais_assignment'])) {
      $bundles['node']['ais_assignment']['class'] = AisAssignment::class;
    }
  }


}
