<?php

namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

/**
 * Custom field to display review count.
 *
 * @ViewsField("test_field")
 */
class TestField extends FieldPluginBase {

  public function query() {}

  public function render(ResultRow $values) {
    return 'Test OK';
  }
}
