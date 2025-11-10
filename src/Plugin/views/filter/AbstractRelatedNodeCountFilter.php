<?php
namespace Drupal\indexing_study\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter;

/**
 * Abstract filter for related node count for documents.
 *
 * This finds the number of "target" nodes which have an entity reference
 * field pointing to the "root" node in question, i.e. a reverse reference.
 */
class AbstractRelatedNodeCountFilter extends NumericFilter {

  /**
   * Bundle of target nodes.
   *
   * @var string
   */
  protected $node_type = '';

  /**
   * Bundle of root nodes.
   *
   * @var string
   */
  protected $root_node_type = '';

  /**
   * Field on the target node that points to the root node.
   *
   * @var string
   */
  protected $relating_field = '';

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $database = \Drupal::database();

    // Create subquery to get document IDs with their review counts
    $subquery = $database->select('node', 'n');
    $subquery->leftJoin('node__' . $this->relating_field, 'fd',
      'n.nid = fd.' . $this->relating_field . '_target_id');
    $subquery->leftJoin('node', 'related_nodes', 'fd.entity_id = related_nodes.nid AND related_nodes.type = :node_type', [
      ':node_type' => $this->node_type,
    ]);

    $subquery->addField('n', 'nid', 'document_id');
    $subquery->addExpression('COUNT(related_nodes.nid)', 'assignment_count');
    $subquery->condition('n.type', $this->root_node_type)
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
