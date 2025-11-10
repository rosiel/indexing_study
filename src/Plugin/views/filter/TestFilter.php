<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\Core\Database\Query\Condition;
use Drupal\indexing_study\IndexingStudyUtils;

/**
 * Custom field to display review count for documents.
 *
 * @ViewsFilter("test_filter")
 */
class TestFilter extends NumericFilter {


  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $database = \Drupal::database();

    // Create subquery to get document IDs with their review counts
    $subquery = $database->select('node', 'n');

    $subquery->leftJoin('node__field_ais_document', 'fd',
      'n.nid = fd.field_ais_document_target_id');
//    $subquery->leftJoin('node', 'nr', 'fd.entity_id = nr.nid AND nr.type = :assignment_type AND nr.status = 1', [
//      ':assignment_type' => IndexingStudyUtils::ASSIGNMENT_BUNDLE,
//    ]);
    $subquery->addField('n', 'nid', 'document_id');
    $subquery->addExpression('COUNT(nid)', 'assignment_count');
    $subquery->groupBy('document_id');
    $subquery->having($this->buildHavingCondition());


//    $subquery->condition('n.type', IndexingStudyUtils::DOCUMENT_BUNDLE)
//      ->condition('n.status', 1)
//      ->groupBy('n.nid')
//      ->having($this->buildHavingCondition());

    // Add the condition to the main query
    $this->query->addWhere($this->options['group'], 'node.nid', $subquery, 'IN');
  }

  /**
   * Build the HAVING condition for the subquery.
   */
  protected function buildHavingCondition() {
    $value = $this->value['value'] ?? NULL;
    $min = $this->value['min'] ?? NULL;
    $max = $this->value['max'] ?? NULL;

    $database = \Drupal::database();
    $condition = new Condition('AND');
    $field = 'assignment_count';
    switch ($this->operator) {
      case '=':
        $condition->condition($field, $value, '=');
        break;

      case '!=':
        $condition->condition('nid', $value, '!=');
        break;

      case '>':
        $condition->condition('nid', $value, '>');
        break;

      case '>=':
        $condition->condition('nid', $value, '>=');
        break;

      case '<':
        $condition->condition('nid', $value, '<');
        break;

      case '<=':
        $condition->condition('nid', $value, '<=');
        break;

      case 'between':
        $condition->condition('nid', $min, '>=');
        $condition->condition('nid', $max, '<=');
        break;

      case 'not between':
        $condition->condition('nid', $min, '<');
        $condition->condition('nid', $max, '>');
        break;

      case 'empty':
        $condition->condition('nid', 0, '=');
        break;

      case 'not empty':
        $condition->condition('nid', 0, '>');
        break;

      default:
        // No condition
        break;
    }

    return $condition;
  }


}
