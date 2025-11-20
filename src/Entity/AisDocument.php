<?php
namespace Drupal\indexing_study\Entity;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\migrate\Plugin\migrate\process\ArrayBuild;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;

class AisDocument extends Node implements  AisDocumentInterface {

  /**
   * The Indexing Study Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig $config
   */
  protected ImmutableConfig $config;

  private function getConfig() {
    if (!isset($this->config)) {
      $this->config = \Drupal::config('indexing_study.settings');
    }
    return $this->config;
  }
  public function getAnalyses(): array
  {
    $config = $this->getConfig();
    $analysis_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $config->get('subject_analysis.bundle'))
      ->condition($config->get('subject_analysis.document_field'), $this->id())
      ->execute();
    return \Drupal::service('indexing_study.utils')->intify_array($analysis_ids);
  }

  public function getConsensus(): array
  {
    $config = $this->getConfig();
    $consensus_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $config->get('consensus.bundle'))
      ->condition($config->get('consensus.document_field'), $this->id())
      ->execute();
    return \Drupal::service('indexing_study.utils')->intify_array($consensus_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedUserIds(): array {
    $assignment_ids = $this->getAssignments();
    $reviewers = [];
    foreach ($assignment_ids as $assignment_id) {
      $assignment = $this->entityTypeManager()->getStorage('node')->load($assignment_id);
      $reviewers[] = $assignment->get($this->config->get('assignment.user_field'))->getValue()[0]['target_id'];
    }
    return $reviewers;
  }

  /**
   * {@inheritdoc }
   */
  public function getAssignments($valid = NULL): array{
    $config = $this->getConfig();
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(False)
      ->condition('type', $config->get('assignment.bundle'))
      ->condition($config->get('assignment.document_field'), $this->id());
    if ($valid !== NULL) {
      switch($valid) {
        case (True):
          $query->condition('status', 1);
          break;
        case (False):
          $query->condition('status', 0);
      }
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function needsAssignment(): bool {
    // TODO: merge with study's needs assignment.
    $assignments = $this->getAssignments();
    if (count($assignments) < 2) {
      return True;
    }
    else {
      $valid = [];
      $invalid = [];
      foreach ($assignments as $assignment_id) {
        $assignment = $this->entityTypeManager()->getStorage('node')->load($assignment_id);
        if ($assignment->isPublished()) {
          $valid[] = $assignment_id;
        }
        else {
          $invalid[] = $assignment_id;
        }
      }
      if (count($invalid) >= 2 or count($valid) >= 2) {
        return False;
      }
    }
    return True;
  }
}
