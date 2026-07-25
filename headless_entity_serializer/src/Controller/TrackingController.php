<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the entity change tracking listing page.
 */
class TrackingController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Constructs a new TrackingController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   */
  public function __construct(
    Connection $database,
    DateFormatterInterface $date_formatter,
    PagerManagerInterface $pager_manager,
  ) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('pager.manager'),
    );
  }

  /**
   * Renders a paginated listing of entity tracking records.
   *
   * @return array
   *   A render array with the tracking table and pager.
   */
  public function listing(): array {
    $header = [
      $this->t('Entity Type'),
      $this->t('Entity ID'),
      $this->t('Operation'),
      $this->t('Last Changed'),
      $this->t('Revision ID'),
    ];

    $limit = 50;
    $page = $this->pagerManager->findPage();
    $offset = $page * $limit;

    $count_query = $this->database->select('hes_entity_tracking', 't');
    $count_query->addExpression('COUNT(*)');

    $query = $this->database->select('hes_entity_tracking', 't');
    $query->fields('t', [
      'entity_type_id',
      'entity_id',
      'operation',
      'changed',
      'revision_id',
    ]);
    $query->orderBy('t.changed', 'DESC');
    $query->range($offset, $limit);

    $total = (int) $count_query->execute()->fetchField();
    $results = $query->execute()->fetchAll();

    $this->pagerManager->createPager($total, $limit);

    $rows = [];
    foreach ($results as $record) {
      $operation_badge = match ($record->operation) {
        'insert' => '<span style="color:green">+ INSERT</span>',
        'update' => '<span style="color:orange">~ UPDATE</span>',
        'delete' => '<span style="color:red">- DELETE</span>',
        default => $record->operation,
      };

      $changed_date = $record->changed > 0
        ? $this->dateFormatter->format($record->changed, 'short')
        : '-';

      $rows[] = [
        $record->entity_type_id,
        $record->entity_id,
        [
          'data' => ['#markup' => $operation_badge],
        ],
        $changed_date,
        $record->revision_id ?: '-',
      ];
    }

    $build = [];

    $build['summary'] = [
      '#markup' => '<p>' . $this->t(
        'Entity changes tracked by the serialization module. Records are cleaned after each incremental run.'
      ) . '</p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No tracking records found.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
