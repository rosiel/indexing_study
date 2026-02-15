<?php
namespace Drupal\indexing_study\Plugin\views\field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom field to display review count.
 *
 */
class AbstractRelatedNodeCountField extends FieldPluginBase implements ContainerFactoryPluginInterface {

  protected $node_type = '';
  protected $root_node_type = '';
  protected $relating_field = '';

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an AbstractRelatedNodeCountField.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory->get('indexing_study.settings');
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): \Drupal\Component\Render\MarkupInterface|int|string|\Drupal\views\Render\ViewsRenderPipelineMarkup
  {
    $entity = $this->getEntity($values);

    if (!$entity instanceof NodeInterface || $entity->bundle() !== $this->root_node_type) {
      return '0';
    }

    $count = $this->getRelatedNodeCount($entity->id());
    return $count ?: '0';
  }

  /**
   * Get the related node count for a document.
   */
  protected function getRelatedNodeCount($document_id): int {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', $this->node_type)
      ->condition($this->relating_field, $document_id)
      ->accessCheck(FALSE);

    // In Drupal 11, count() returns int directly
    return $query->count()->execute();
  }

  public function clickSortable()
  {
    return FALSE;
  }
}
