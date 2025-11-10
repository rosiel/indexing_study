<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\Core\Database\Query\Condition;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display review count for documents.
 *
 * @ViewsFilter("assignment_count_filter")
 */
class AssignmentCountFilter extends NumericFilter {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $database = \Drupal::database();

    // Create subquery to get document IDs with their review counts
    $subquery = $database->select('node', 'n');
    $subquery->leftJoin('node__' . IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD, 'fd',
      'n.nid = fd.' . IndexingStudyUtils::ASSIGNMENT_DOCUMENT_FIELD . '_target_id');
    $subquery->leftJoin('node', 'related_nodes', 'fd.entity_id = related_nodes.nid AND related_nodes.type = :assignment_type', [
      ':assignment_type' => IndexingStudyUtils::ASSIGNMENT_BUNDLE,
    ]);

    $subquery->addField('n', 'nid', 'document_id');
    $subquery->addExpression('COUNT(related_nodes.nid)', 'assignment_count');
    $subquery->condition('n.type', IndexingStudyUtils::DOCUMENT_BUNDLE)
      ->groupBy('n.nid');

    $having_condition = $this->buildHavingCondition();
    if ($having_condition) {
      $subquery->having($having_condition);
    }
    $matching_document_ids = $subquery->execute()->fetchCol();

    if (!empty($matching_document_ids)) {
      // Add WHERE condition to the main query
      $this->query->addWhere($this->options['group'], 'node.nid', $matching_document_ids, 'IN');
    }
    else {
      // If no documents match, ensure no results
      $this->query->addWhere($this->options['group'], '1', '0', '=');
    }

  }

  /**
   * Build the HAVING condition for the subquery.
   */
  protected function buildHavingCondition() {
    $value = $this->value['value'] ?? NULL;
    $min = $this->value['min'] ?? NULL;
    $max = $this->value['max'] ?? NULL;

    switch ($this->operator) {
      case '=':
        return "COUNT(related_nodes.nid) = " . (int)$value;

      case '!=':
        return "COUNT(related_nodes.nid) != " . (int)$value;

      case '>':
        return "COUNT(related_nodes.nid) > " . (int)$value;

      case '>=':
        return "COUNT(related_nodes.nid) >= " . (int)$value;

      case '<':
        return "COUNT(related_nodes.nid) < " . (int)$value;

      case '<=':
        return "COUNT(related_nodes.nid) <= " . (int)$value;

      case 'between':
        return "COUNT(related_nodes.nid) BETWEEN " . (int)$min . " AND " . (int)$max;

      case 'not between':
        return "COUNT(related_nodes.nid) NOT BETWEEN " . (int)$min . " AND " . (int)$max;

      case 'empty':
        return "COUNT(related_nodes.nid) = 0";

      case 'not empty':
        return "COUNT(related_nodes.nid) > 0";

      default:
        return NULL;
    }

  }


}
