<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service for tracking entity changes in the hes_entity_tracking table.
 */
class EntityTrackingService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new EntityTrackingService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * Tracks an entity change in the hes_entity_tracking table.
   *
   * Uses database merge (upsert) to atomically record the change.
   * Only tracks entity types configured in entity_types or
   * entity_types_inline.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $operation
   *   The operation: insert, update, or delete.
   */
  public function trackChange(EntityInterface $entity, string $operation): void {
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();

    if ($entity_id === NULL) {
      return;
    }

    if (!$this->isTrackedEntityType($entity_type_id)) {
      return;
    }

    $revision_id = 0;
    if ($entity instanceof ContentEntityInterface
      && $entity->getEntityType()->isRevisionable()) {
      $revision_id = (int) $entity->getLoadedRevisionId();
    }

    $this->database->merge('hes_entity_tracking')
      ->keys([
        'entity_type_id' => $entity_type_id,
        'entity_id' => (string) $entity_id,
      ])
      ->fields([
        'changed' => time(),
        'operation' => $operation,
        'revision_id' => $revision_id,
      ])
      ->execute();
  }

  /**
   * Checks if an entity type is configured for tracking.
   *
   * An entity type is tracked if it appears in either entity_types
   * or entity_types_inline configuration.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   TRUE if the entity type is tracked, FALSE otherwise.
   */
  public function isTrackedEntityType(string $entity_type_id): bool {
    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entity_types = $config->get('entity_types') ?? [];
    $entity_types_inline = $config->get('entity_types_inline') ?? [];
    $tracked = array_merge($entity_types, $entity_types_inline);

    return in_array($entity_type_id, $tracked, TRUE);
  }

}
