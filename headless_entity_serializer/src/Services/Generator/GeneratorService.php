<?php

namespace Drupal\headless_entity_serializer\Services\Generator;

/**
 *
 */
class GeneratorService {

  private $fileStorageManager;

  private $configFactory;

  private $entitySerializer;

  /**
   * Propiedad para el servicio de estado.
   */
  protected $state;

  /**
   *
   */
  public function __construct(
    $file_storage_manager,
    $config_factory,
    $entity_serializer,
    $state,
  ) {
    $this->fileStorageManager = $file_storage_manager;
    $this->configFactory = $config_factory;
    $this->entitySerializer = $entity_serializer;
    $this->state = $state;
  }

  /**
   *
   */
  public function fullGenerate() {

    // Remove all files.
    $this->fileStorageManager->deleteAllSerializedFiles();

    // Recreate all files.
    $this->fileStorageManager->createDirectory();
    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entityTypes = $config->get('entity_types');
    foreach ($entityTypes as $entityType) {
      $storage = \Drupal::entityTypeManager()->getStorage($entityType);
      $entityIds = $storage->getQuery()->execute();
      foreach ($entityIds as $entityId) {
        $entity = $storage->load($entityId);
        $this->entitySerializer->exportEntity($entity);
      }
    }
  }

  /**
   *
   */
  public function incrementalGenerate() {
    $last_run_timestamp = $this->state->get('headless_entity_serializer.last_incremental_run', 0);
    $current_timestamp = time();

    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $entityTypes = $config->get('entity_types');

    foreach ($entityTypes as $entityType) {
      $storage = \Drupal::entityTypeManager()->getStorage($entityType);
      // Consultar las últimas revisiones.
      $query = $storage->getQuery()->latestRevision();
      $changed_entity_ids = $query
        ->condition('changed', $last_run_timestamp, '>')
        ->execute();
      $created_entity_ids = $query
        ->condition('created', $last_run_timestamp, '>')
        ->execute();

      $ids_to_process = array_unique(array_merge($changed_entity_ids, $created_entity_ids));
      foreach ($ids_to_process as $entityId) {
        $entity = $storage->load($entityId);
        $this->entitySerializer->exportEntity($entity);
      }
      $this->removeFileNotInDataBase($storage, $entityType);

    }

    $this->state->set('headless_entity_serializer.last_incremental_run', $current_timestamp);
  }

  /**
   *
   */
  public function removeFileNotInDataBase($storage, $entityType) {

    // Remove entity.
    // Siempre consulta la última revisión.
    $query = $storage->getQuery()->latestRevision();
    $current_db_ids = $query->execute();

    $serialized_files_info = $this->fileStorageManager->getEntitiesInFiles($entityType);
    $serialized_entity_ids = array_keys($serialized_files_info);

    // Files directory to remove.
    $deleted_entity_ids = array_diff($serialized_entity_ids, $current_db_ids);
    foreach ($deleted_entity_ids as $deleted_entity_id) {
      $this->fileStorageManager->deleteEntityDirectory($entityType, $deleted_entity_id);
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
    // Devuelve array de objetos LanguageInterface.
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
          $this->fileStorageManager->deleteEntityFile($entityType, $deleted_entity_id, $language_id);
        }
      }

    }

  }

}
