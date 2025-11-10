<?php

function indexing_study_views_data_alter(&$data) {
  $data['node']['test_field'] = [
    'title' => t('Test Field'),
    'field' => [
      'title' => t('Test Field'),
      'id' => 'test_field',
    ],
    'filter' => [
      'title' => t('TEST Filter'),
      'id' => 'test_filter',
    ],
  ];
  $data['node']['assignment_count'] = [
    'title' => t('Assigment Count'),
    'help' => t('foobar'),
    'field' => [
      'title' => t('Assigment Count Field'),
      'help' => t("Display the number of assignments associated with a document."),
      'id' => 'assignment_count_field',
    ],
    'filter' => [
      'title' => t('Assigment Count Filter'),
      'help' => t("Limit by the number of assignments associated with a document"),
      'id' => 'assignment_count_filter',
    ],
  ];
}
