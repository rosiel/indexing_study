<?php

namespace Drupal\indexing_study\Entity;

use Exception;

interface AisStudyInterface extends AbstractAisNodeInterface {

  /**
   * Get list of user objects assigned to this study.
   *
   * @return array
   */
  public function getReviewers(): array;

  /**
   * Create all assignments (for subject analysis) for this study.
   *
   * Called when submitting the IndexingStudyAssignmentForm.
   *
   * @throws Exception
   */
  public function createAssignments(array $reviewers): bool;



}


