<?php

namespace Drupal\headless_entity_serializer\Services\Generator;

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
   * @var \Drupal\headless_entity_serializer\Storage\FileStorageManager
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
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GeneratorService object.
   *
   * @param \Drupal\headless_entity_serializer\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\headless_entity_serializer\Services\Serialize\EntitySerializer $entity_serializer
   *   The entity serializer service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannel $logger_factory
   *   The logger factory channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    $file_storage_manager,
    $config_factory,
    $entity_serializer,
    $state,
    $logger_factory,
    $entity_type_manager,
  ) {
    $this->fileStorageManager = $file_storage_manager;
    $this->configFactory = $config_factory;
    $this->entitySerializer = $entity_serializer;
    $this->state = $state;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   *
   */
  public function resetState() {
    $this->state->set('headless_entity_serializer.last_incremental_run', 0);

  }

  /**
   * Fully generate JSON files for a selected entity type.
   *
   * This command generate all existing serialized files for the
   * entity type passed by parameter.
   */
  public function fullGenerateEntityType($entity_type_id) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entityIds = $storage->getQuery()->execute();
    foreach ($entityIds as $entityId) {
      $entity = $storage->load($entityId);
      $this->entitySerializer->exportEntity($entity);
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

    // Get timestamp for state.
    $current_timestamp = time();

    // Remove all files.
    $this->fileStorageManager->deleteAllSerializedFiles();

    // Create a directory for entities files.
    $this->fileStorageManager->createDirectory();

    // Get the entities types for generate JSON files.
    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entityTypes = $config->get('entity_types');

    // Generate all entities in JSON files by types.
    foreach ($entityTypes as $entityType) {
      $this->fullGenerateEntityType($entityType);
    }

    // Generete the alias files.
    $this->generateAlias();

    // Set state.
    $this->state->set('headless_entity_serializer.last_incremental_run', $current_timestamp);

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

  /**
   * Performs an incremental update of serialized entity JSON files.
   *
   * This command identifies new, updated, or deleted entities
   * since the last incremental run and processes them.
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

      $query = $storage->getQuery()->latestRevision();
      $changed_entity_ids = $query
        ->condition("changed", $last_run_timestamp, '>')
        ->execute();

      $created_entity_ids = $query
        ->condition('created', $last_run_timestamp, '>')
        ->execute();

      $ids_to_process = array_unique(array_merge($changed_entity_ids, $created_entity_ids));
      $totalEntities = count($ids_to_process);
      $this->logger->info('Init generation for entity type: {entityType}. Total exported: {count}.',
      ['entityType' => $entityType, 'count' => count($ids_to_process)]);

      $progress = 0;
      $priorPercentage = 0;
      foreach ($ids_to_process as $entityId) {
        $entity = $storage->load($entityId);
        $this->entitySerializer->exportEntity($entity);
        $progress++;
        $percentage = round(($progress / $totalEntities) * 100);

        if ($priorPercentage != $percentage) {
          $priorPercentage = $percentage;
          $this->logger->info('Processed {current} of {total} entities for {type} ({percentage}%).', [
            'current' => $progress,
            'total' => $totalEntities,
            'type' => $entityType,
            'percentage' => $percentage,
          ]);
        }

      }
      $this->logger->info('Cleaning files....');
      $this->removeFileNotInDataBase($storage, $entityType);

      $this->logger->info('Generating Alias....');
      $this->generateAlias();
    }

    $this->state->set('headless_entity_serializer.last_incremental_run', $current_timestamp);

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

  /**
   * Identifies and removes serialized entity files that no exist in the DB.
   *
   * This method first checks for completely deleted entities (ID not in DB),
   * then checks for deleted translations of existing entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityStorageInterface $storage
   *   The entity storage handler for the current entity type.
   * @param string $entity_type_id
   *   The ID of the entity type being processed.
   *
   * @return array
   *   An associative array with 'status' (bool), 'count' (int of deleted files), and 'errors' (array of error messages).
   */
  public function removeFileNotInDataBase($storage, $entity_type_id) {

    // Remove entity.
    // Siempre consulta la última revisión.
    $query = $storage->getQuery()->latestRevision();
    $current_db_ids = $query->execute();
    $serialized_files_info = $this->fileStorageManager->getEntitiesInFiles($entity_type_id);
    $serialized_entity_ids = array_keys($serialized_files_info);

    // Files directory to remove.
    $deleted_entity_ids = array_diff($serialized_entity_ids, $current_db_ids);

    foreach ($deleted_entity_ids as $deleted_entity_id) {
      $this->fileStorageManager->deleteEntityDirectory($entity_type_id, $deleted_entity_id);
    }

    // Translation to remove.
    $grouped = [];

    foreach ($serialized_files_info as $id => $langs) {
      foreach ($langs as $langcode) {
        // Si aún no existe ese idioma en el nuevo array, lo creamos.
        if (!isset($grouped[$langcode])) {
          $grouped[$langcode] = [];
        }
        $grouped[$langcode][] = $id;
      }
    }

    $language_manager = \Drupal::languageManager();
    $languages = $language_manager->getLanguages();

    foreach ($languages as $language_id => $value) {

      if (array_key_exists($language_id, $grouped)) {
        $query = $storage->getQuery()->latestRevision()
            // Filtrar por idioma.
          ->condition('langcode', $language_id);
        $current_db_ids = $query->execute();
        $serialized_entity_ids = $grouped[$language_id];
        $deleted_entity_ids = array_diff($serialized_entity_ids, $current_db_ids);
        foreach ($deleted_entity_ids as $deleted_entity_id) {
          $this->fileStorageManager->deleteEntityFile($entity_type_id, $deleted_entity_id, $language_id);
        }
      }

    }

    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

  /**
   * Generates and saves JSON files for all path aliases.
   *
   * This method queries all path aliases from the database,
   * groups them by language, and saves each language's aliases into
   * a separate JSON file.
   *
   * The structure is: [destination_directory]/alias/[langcode].json.
   *
   * @return bool
   *   TRUE if all alias files were generated successfully, FALSE otherwise.
   */
  public function generateAlias() {
    $aliases = \Drupal::database()->select('path_alias', 'pa')
      ->fields('pa', ['path', 'alias', 'langcode'])
      ->execute()
      ->fetchAll();

    $result = [];

    foreach ($aliases as $key => $aliasEntity) {
      $langcode = $aliasEntity->langcode;
      $path = $aliasEntity->path;
      $alias = $aliasEntity->alias;

      $key = $alias;

      if (!array_key_exists($langcode, $result)) {
        $result[$langcode] = [];
      }
      $path = str_replace("/", "-", $path);
      if (!empty($path) && $path[0] === '-') {
        $path = substr($path, 1);
      }
      $result[$langcode][$key] = $path;
    }

    foreach ($result as $key => $value) {
      $jsonData = json_encode($result[$key]);
      $this->fileStorageManager->saveData($jsonData, "alias", "alias", $key);

    }
    return [
      "status" => TRUE,
      "message" => "The generation was successful",
    ];
  }

}
