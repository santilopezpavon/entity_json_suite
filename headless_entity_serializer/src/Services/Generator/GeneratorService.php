<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Services\Generator;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for generating full and incremental sets of serialized entities.
 *
 * This service orchestrates the serialization process, interacting with
 * file storage, configuration, entity management, state, database, and
 * messenger APIs.
 */
class GeneratorService {

  /**
   * The file storage manager service.
   *
   * @var \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager
   */
  private $fileStorageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The entity serializer service.
   *
   * @var \Drupal\headless_entity_serializer\Services\Serialize\EntitySerializer
   */
  private $entitySerializer;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new GeneratorService object.
   *
   * @param \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\headless_entity_serializer\Services\Serialize\EntitySerializer $entity_serializer
   *   The entity serializer service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    $file_storage_manager,
    $config_factory,
    $entity_serializer,
    $state,
    LoggerChannelFactoryInterface $logger_factory,
    $entity_type_manager,
    LanguageManagerInterface $language_manager,
    Connection $database,
  ) {
    $this->fileStorageManager = $file_storage_manager;
    $this->configFactory = $config_factory;
    $this->entitySerializer = $entity_serializer;
    $this->state = $state;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->database = $database;
  }

  /**
   * Resets the incremental update state timestamp.
   */
  public function resetState() {
    $this->state->set('headless_entity_serializer.last_incremental_run', 0);
  }

  /**
   * Fully generates JSON files for a single entity type.
   *
   * This method exports all entities of the given type to JSON files.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g., 'node').
   */
  public function fullGenerateEntityType($entity_type_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entityIds = $storage->getQuery()->accessCheck(FALSE)->execute();

    foreach (array_chunk($entityIds, 100) as $chunk) {
      $entities = $storage->loadMultiple($chunk);
      foreach ($entities as $entity) {
        if ($entity instanceof ContentEntityInterface) {
          $this->entitySerializer->exportEntity($entity);
        }
      }
    }
  }

  /**
   * Fully regenerates JSON files for selected entity types and path aliases.
   *
   * This command deletes all existing serialized files for the configured
   * entity types and path aliases, and then re-generates them from scratch.
   *
   * @return array
   *   An associative array with 'status' (bool) and 'message' (string).
   */
  public function fullGenerate() {

    $current_timestamp = time();

    $this->fileStorageManager->deleteAllSerializedFiles();

    $this->fileStorageManager->createDirectory();

    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entityTypes = $config->get('entity_types');

    foreach ($entityTypes as $entityType) {
      $this->fullGenerateEntityType($entityType);
    }

    // Clear all tracking entries after full regeneration.
    $this->database->delete('hes_entity_tracking')
      ->execute();

    $this->state->set('headless_entity_serializer.last_incremental_run', $current_timestamp);

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

  /**
   * Performs an incremental update of serialized entity JSON files.
   *
   * Uses the hes_entity_tracking table to identify entities that have been
   * created, updated, or deleted since the last incremental run. This works
   * regardless of whether the entity type has a "changed" base field.
   *
   * @return array
   *   An associative array with 'status' (bool) and 'message' (string).
   */
  public function incrementalGenerate() {
    $last_run_timestamp = $this->state->get('headless_entity_serializer.last_incremental_run', 0);
    $current_timestamp = time();

    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entityTypes = $config->get('entity_types');

    foreach ($entityTypes as $entityType) {

      $storage = $this->entityTypeManager->getStorage($entityType);

      // Get entity IDs with changes (inserts and updates) from tracking table.
      $changed_entity_ids = $this->database->select('hes_entity_tracking', 't')
        ->fields('t', ['entity_id'])
        ->condition('t.entity_type_id', $entityType)
        ->condition('t.changed', $last_run_timestamp, '>')
        ->condition('t.operation', ['insert', 'update'], 'IN')
        ->execute()
        ->fetchCol();

      // Get entity IDs that were deleted since last run.
      $deleted_entity_ids = $this->database->select('hes_entity_tracking', 't')
        ->fields('t', ['entity_id'])
        ->condition('t.entity_type_id', $entityType)
        ->condition('t.changed', $last_run_timestamp, '>')
        ->condition('t.operation', 'delete')
        ->execute()
        ->fetchCol();

      // Process deleted entities first.
      foreach ($deleted_entity_ids as $deleted_id) {
        $this->fileStorageManager->deleteEntityDirectory($entityType, $deleted_id);
      }

      $totalEntities = count($changed_entity_ids);
      $this->logger->info('Init generation for entity type: {entityType}. Total exported: {count}.',
      ['entityType' => $entityType, 'count' => $totalEntities]);

      $progress = 0;
      $priorPercentage = 0;

      foreach (array_chunk($changed_entity_ids, 100) as $chunk) {
        $entities = $storage->loadMultiple($chunk);
        foreach ($entities as $entity) {
          if ($entity instanceof ContentEntityInterface) {
            $this->entitySerializer->exportEntity($entity);
          }

          if ($totalEntities > 0) {
            $progress++;
            $percentage = (int) round(($progress / $totalEntities) * 100);

            if ($priorPercentage !== $percentage) {
              $priorPercentage = $percentage;
              $this->logger->info('Processed {current} of {total} entities for {type} ({percentage}%).', [
                'current' => $progress,
                'total' => $totalEntities,
                'type' => $entityType,
                'percentage' => $percentage,
              ]);
            }
          }
        }
      }
      $this->logger->info('Cleaning files....');
      $this->removeFileNotInDataBase($storage, $entityType);

    }

    // Clean processed tracking entries (non-delete operations).
    $this->database->delete('hes_entity_tracking')
      ->condition('changed', $current_timestamp, '<=')
      ->condition('operation', 'delete', '!=')
      ->execute();

    // Keep delete tracking entries until the next full generation,
    // so orphaned files can still be cleaned up if incremental runs
    // multiple times before a full regeneration.
    $this->state->set('headless_entity_serializer.last_incremental_run', $current_timestamp);

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

  /**
   * Removes serialized files that no longer correspond to database entities.
   *
   * This method first checks for completely deleted entities (ID not in DB),
   * then checks for deleted translations of existing entities.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage handler for the current entity type.
   * @param string $entity_type_id
   *   The ID of the entity type being processed.
   *
   * @return array
   *   An associative array with 'status' (bool), 'count' (int), and
   *   'errors' (array).
   */
  public function removeFileNotInDataBase($storage, $entity_type_id) {

    // Remove deleted entities.
    $query = $storage->getQuery()->accessCheck(FALSE);
    if ($storage->getEntityType()->isRevisionable()) {
      $query->latestRevision();
    }
    $current_db_ids = $query->execute();
    $serialized_files_info = $this->fileStorageManager->getEntitiesInFiles($entity_type_id);
    $serialized_entity_ids = array_keys($serialized_files_info);

    $deleted_entity_ids = array_diff($serialized_entity_ids, $current_db_ids);

    foreach ($deleted_entity_ids as $deleted_entity_id) {
      $this->fileStorageManager->deleteEntityDirectory($entity_type_id, $deleted_entity_id);
    }

    // Remove orphaned translations.
    // For each entity that still exists in the DB, check that all its
    // serialized translation files still have a corresponding translation.
    $existing_ids = array_intersect($serialized_entity_ids, $current_db_ids);

    foreach (array_chunk($existing_ids, 100) as $chunk) {
      $loaded_entities = $storage->loadMultiple($chunk);
      foreach ($chunk as $entity_id) {
        $entity = $loaded_entities[$entity_id] ?? NULL;
        $serialized_langs = $serialized_files_info[$entity_id] ?? [];

        foreach ($serialized_langs as $langcode) {
          if (!$entity instanceof ContentEntityInterface || !$entity->hasTranslation($langcode)) {
            $this->fileStorageManager->deleteEntityFile($entity_type_id, (string) $entity_id, $langcode);
          }
        }
      }
    }

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

}
