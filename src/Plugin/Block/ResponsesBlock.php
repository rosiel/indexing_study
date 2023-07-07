<?php
namespace Drupal\indexing_study\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\user\Entity\User;
use Drupal\indexing_study\IndexingStudyUtils;
use Drupal\Core\Url;

/**
 * Provides a block for users to start their responses.
 *
 * @Block(
 *   id = "indexing_study_responses",
 *   admin_label = @Translation("Indexing Study Responses"),
 *   category = @Translation("Indexing Study"),
 * )
 */

class ResponsesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $utils = \Drupal::service('indexing_study.utils');
    $header = [
      'actions' => 'Actions',
      'pool_id' => 'Pool ID',
      'progress' => 'Progress',
    ];
    $pools = $utils->getPools();
    $rows = [];
    $link_generator = \Drupal::service('link_generator');
    foreach ($pools as $pool_id) {
      $pool = \Drupal::entityTypeManager()->getStorage('storage')->load($pool_id);
      $assignments = $utils->getAssignmentsInPool($pool, User::load(\Drupal::currentUser()->id()));
      $assignments_todo = $utils->getAssignmentsToDoInPool($pool, User::load(\Drupal::currentUser()->id()));
      if (count($assignments_todo) == 0) {
        $link = '';
        $progress = 'You have completed all ' . count($assignments) . '.';
      }
      else {
        $link = $link_generator->generate('Enter responses!',Url::fromRoute('indexing_study.respond', ['storage' => $pool_id]));
        $progress = 'You have ' . count($assignments_todo) . ' todo out of ' . count($assignments) . '.';
      }

      // If there are more to do... else skip this.
      $rows[] = array(
        $link,
        $pool->label(),
        $progress,
      );
    }
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }
  /**
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

}