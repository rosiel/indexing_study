<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\indexing_study\Plugin\views\filter\AbstractRelatedNodeCountFilter;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display subject analysis count for documents.
 *
 * @ViewsFilter("subject_analysis_count_filter")
 */
class SubjectAnalysisCountFilter extends AbstractRelatedNodeCountFilter {

  public function defaultExposeOptions() {
    parent::defaultExposeOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    return parent::defineOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->buildQuery(IndexingStudyUtils::SUBJECT_ANALYSIS_BUNDLE);
  }

}
