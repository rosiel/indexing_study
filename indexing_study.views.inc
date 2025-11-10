<?php

function indexing_study_views_data_alter(&$data) {

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
}
