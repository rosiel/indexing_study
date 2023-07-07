<?php

namespace Drupal\indexing_study;

use Drupal\storage\Entity\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bibcite_entity\Entity\ReferenceInterface;
use Drupal\user\UserInterface;

class IndexingStudyUtils {
  // Store the field on reference that points to the pool.
  const MEMBER_OF_POOL_FIELD = 'field_pool';
  // Store the machine name of the 'assignment' bundle.
  const ASSIGNMENT_BUNDLE = 'assignment';
  // Store teh machine name of the 'pool' bundle.
  const POOL_BUNDLE = 'pool';
  // Store the field on assignment that points to the pool.
  const ASSIGNMENT_POOL_FIELD = 'field_pool';
  // Store the field on assignment that points to user.
  const ASSIGNMENT_USER_FIELD = 'field_user';
  // Store the field on assignment that points to a citation item.
  const ASSIGNMENT_CITATION_FIELD = 'field_citation';
  // Store the field on a response that points to an assignment
  const RESPONSE_ASSIGNMENT_FIELD = 'field_assignment';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get a list of all pools.
   *
   * @return array
   */
  public function getPools() {
    return $this->entityTypeManager->getStorage('storage')
      ->getQuery()
      ->condition('type', self::POOL_BUNDLE)
      ->accessCheck(TRUE)
      ->execute();
  }

  /**
   * @param \Drupal\storage\Entity\StorageInterface $pool
   * @return array
   */

  public function getReferencesInPool(StorageInterface $pool) {
    if (!$this->entityTypeManager->getStorage('field_storage_config')->load('bibcite_reference.' . self::MEMBER_OF_POOL_FIELD)) {
      return [];
    }
    $reference_ids = $this->entityTypeManager->getStorage('bibcite_reference')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::MEMBER_OF_POOL_FIELD, $pool->id())
      ->execute();
    if (empty($reference_ids)) {
      return [];
    }
    return $this->entityTypeManager->getStorage('bibcite_reference')->loadMultiple($reference_ids);
  }

  public function getAssignmentsForReference(ReferenceInterface $reference) {
    if (!$this->entityTypeManager->getStorage('field_storage_config')->load('storage.' . self::ASSIGNMENT_POOL_FIELD)) {
      return [];
    }
    $assignment_ids = $this->entityTypeManager->getStorage('storage')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::MEMBER_OF_POOL_FIELD, $reference->id())
      ->execute();
    if (empty($assignment_ids)) {
      return [];
    }
    return $this->entityTypeManager->getStorage('bibcite_reference')->loadMultiple($assignment_ids);
  }

  public function countAssignments(ReferenceInterface $reference) {
    if (!$this->entityTypeManager->getStorage('field_storage_config')->load('storage.' . self::ASSIGNMENT_POOL_FIELD)) {
      return [];
    }
    $assignment_ids = $this->entityTypeManager->getStorage('storage')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::MEMBER_OF_POOL_FIELD, $reference->id())
      ->execute();
    if (empty($assignment_ids)) {
      return [];
    }
    return count($assignment_ids);
  }

  /**
   * @param \Drupal\storage\Entity\StorageInterface $pool
   * @param \Drupal\user\Entity\User $user
   * @return array
   */

  public function getAssignmentsInPool(StorageInterface $pool, UserInterface $user) {
    $assignment_ids = $this->entityTypeManager->getStorage('storage')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::ASSIGNMENT_POOL_FIELD, $pool->id())
      ->condition(self::ASSIGNMENT_USER_FIELD, $user->id())
      ->execute();
    return $assignment_ids;
  }

  /**
   * @param \Drupal\storage\Entity\StorageInterface $pool
   * @param \Drupal\user\Entity\User $user
   * @return array
   */

  public function getAssignmentsToDoInPool(StorageInterface $pool, UserInterface $user) {
    $assignment_ids = $this->entityTypeManager->getStorage('storage')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::ASSIGNMENT_POOL_FIELD, $pool->id())
      ->condition(self::ASSIGNMENT_USER_FIELD, $user->id())
      ->execute();
    $assignment_ids = array_unique($assignment_ids);
    if (count($assignment_ids) == 0) {
      return [];
    }
    // Get all responses that point to those assignments
    $response_ids = $this->entityTypeManager->getStorage('storage')->getQuery()
      ->accessCheck(TRUE)
      ->condition(self::RESPONSE_ASSIGNMENT_FIELD, $assignment_ids, 'IN')
      ->execute();
    // Get the "completed" assignment IDs
    $completed_assignments = [];
    $responses = $this->entityTypeManager->getStorage('storage')->loadMultiple($response_ids);
    foreach ($responses as $response) {
      $assignment_id = $response->get(self::RESPONSE_ASSIGNMENT_FIELD)->getValue()[0]['target_id'];
      if ($assignment_id) {
        if (!in_array($assignment_id, $completed_assignments)) {
          $completed_assignments[] = $assignment_id;
        }
      }
    }
    return array_diff($assignment_ids, $completed_assignments);
  }
}
