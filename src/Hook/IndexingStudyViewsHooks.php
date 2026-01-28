<?php
declare(strict_types=1);

namespace Drupal\indexing_study\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class IndexingStudyViewsHooks {
  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  function viewsDataAlter(&$data) {

    $data['node']['assignment_count'] = [
      'title' => t('Assigment Count'),
      'field' => [
        'title' => t('Assigment Count'),
        'help' => t("Display the number of assignments associated with a document."),
        'id' => 'assignment_count_field',
      ],
      'filter' => [
        'title' => t('Assigment Count'),
        'help' => t("Limit by the number of assignments associated with a document"),
        'id' => 'assignment_count_filter',
      ],
    ];
    $data['node']['subject_analysis_count'] = [
      'title' => t('Subject Analysis Count'),
      'field' => [
        'title' => t('Subject Analysis Count'),
        'help' => t("Display the number of Subject Analyses associated with a document."),
        'id' => 'subject_analysis_count_field',
      ],
      'filter' => [
        'title' => t('Subject Analysis Count'),
        'help' => t("Limit by the number of Subject Analyses associated with a document"),
        'id' => 'subject_analysis_count_filter',
      ],
    ];
    $data['node']['agreement_assignment_count'] = [
      'title' => t('Agreement Assignment Count'),
      'field' => [
        'title' => t('Agreement Assignment Count'),
        'help' => t("Display the number of Agreement Assignments associated with a document."),
        'id' => 'agreement_assignment_count_field',
      ],
      'filter' => [
        'title' => t('Agreement Assignment Count'),
        'help' => t("Limit by the number of Agreement Assignments associated with a document"),
        'id' => 'agreement_assignment_count_filter',
      ],
    ];
    $data['node']['agreement_count'] = [
      'title' => t('Agreement Count'),
      'field' => [
        'title' => t('Agreement Count'),
        'help' => t("Display the number of Agreements associated with a document."),
        'id' => 'agreement_count_field',
      ],
      'filter' => [
        'title' => t('Agreement Count'),
        'help' => t("Limit by the number of Agreements associated with a document"),
        'id' => 'agreement_count_filter',
      ],
    ];
    $data['node']['consensus_count'] = [
      'title' => t('Consensus Count'),
      'field' => [
        'title' => t('Consensus Count'),
        'help' => t("Display the number of Consensuses associated with a document."),
        'id' => 'consensus_count_field',
      ],
      'filter' => [
        'title' => t('Consensus Count'),
        'help' => t("Limit by the number of Consensuses associated with a document"),
        'id' => 'consensus_count_filter',
      ],
    ];
    $data['node']['conclusion_count'] = [
      'title' => t('Conclusion Count'),
      'field' => [
        'title' => t('Conclusion Count'),
        'help' => t("Display the number of Conclusions associated with a document."),
        'id' => 'conclusion_count_field',
      ],
      'filter' => [
        'title' => t('Conclusion Count'),
        'help' => t("Limit by the number of Conclusions associated with a document"),
        'id' => 'conclusion_count_filter',
      ],
    ];
  }
}

