<?php

namespace Drupal\headless_entity_serializer\Services\Storage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to manage file system operations for serialized entities.
 *
 * This includes bulk deletion and scanning for existing files.
 */
class FileStorageManager {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new FileStorageManager object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    FileSystemInterface $file_system,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    LanguageManagerInterface $language_manager,
  ) {
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    $this->languageManager = $language_manager;
  }

  /**
   * Ensures the base destination directory exists.
   *
   * This method attempts to create the base directory where all serialized
   * files will be stored. It's often implicitly called by methods that save
   * data, but can be called explicitly to ensure setup.
   *
   * @return bool
   *   TRUE on successful creation or if it already exists, FALSE on failure.
   */
  public function createDirectory(): bool {
    $directory = $this->getBaseDirectory();
    if (!$directory) {
      return FALSE;
    }

    try {
      return $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    }
    catch (\Throwable $e) {
      $this->logger->error('No se pudo crear el directorio base de serialización "{directory}": @message', [
        'directory' => $directory,
        '@message' => $e->getMessage(),
      ]);
      // Re-throw the specific exception.
      throw $e;
    }
  }

  /**
   * Saves JSON data for a specific entity translation to a file.
   *
   * This method constructs the full path for the entity's JSON file based
   * on the configured base directory, entity type, entity ID, and language.
   * It ensures the necessary subdirectories exist before saving.
   *
   * @param string $json_data
   *   The JSON string to save.
   * @param string $entity_id
   *   The ID of the entity.
   * @param string $entity_type_id
   *   The entity type ID (e.g., 'node', 'user').
   * @param string $language_id
   *   The language code for the specific translation (e.g., 'en', 'es').
   *
   * @return bool
   *   TRUE on successful save, FALSE on failure.
   */
  public function saveData($json_data, $entity_id, $entity_type_id, $language_id) {
    $directory = $this->getEntityDirectory($entity_type_id, $entity_id);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $path = $directory . "" . $language_id . ".json";
    $this->fileSystem->saveData($json_data, $path, FileSystemInterface::EXISTS_REPLACE);

    return TRUE;
  }

  /**
   * Gets the specific directory for an entity, including the bucket structure.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The ID of the entity.
   *
   * @return string|false
   *   The entity-specific directory path, or FALSE if the base directory
   *   is not configured.
   */
  private function getEntityDirectory($entity_type_id, $entity_id) {
    $directory = $this->getBaseDirectory();
    if (is_numeric($entity_id)) {
      $bucket = floor($entity_id / 1000);
      return $directory . "/" . $entity_type_id . "/" . $bucket . "/" . $entity_id . "/";
    }
    return $directory . "/" . $entity_type_id . "/" . $entity_id . "/";

  }

  /**
   * Gets information about currently serialized files for a given entity type.
   *
   * This method scans the file system for JSON files belonging to a specific
   * entity type. It expects a directory structure like:
   * [destination_directory]/[entity_type_id]/[entity_id]/[langcode].json.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g., 'node', 'user').
   *
   * @return array
   *   An associative array where keys are entity IDs and values are
   *   arrays of language codes representing available translations.
   *   Example: `['123' => ['en', 'es']]`
   *   Returns an empty array if the base directory is not configured or the
   *   entity type directory does not exist.
   */
  public function getEntitiesInFiles($entity_type_id) {
    $directory = $this->getBaseDirectory() . "/" . $entity_type_id;
    $resultado = [];

    try {
      $files = $this->fileSystem->scanDirectory($directory, '/.*/');
      foreach ($files as $uri => $file_info) {
        if (preg_match('#' . $entity_type_id . '/\d+/(\d+)/(\w+)\.json$#', $uri, $coincidencias)) {
          $id = $coincidencias[1];
          $idioma = $coincidencias[2];
          if (!isset($resultado[$id])) {
            $resultado[$id] = [];
          }
          if (!in_array($idioma, $resultado[$id])) {
            $resultado[$id][] = $idioma;
          }
        }
      }
    }
    catch (\Throwable $th) {
      // Throw $th;.
    }

    return $resultado;
  }

  /**
   * Deletes the entire base directory where serialized files are stored.
   *
   * This effectively removes all serialized entity files.
   *
   * @return bool
   *   TRUE on successful deletion, FALSE otherwise.
   */
  public function deleteAllSerializedFiles(): bool {
    try {
      $base_directory = $this->getBaseDirectory();
      $this->fileSystem->deleteRecursive($base_directory . "/");
      return TRUE;
    }
    catch (\Throwable $th) {
      return FALSE;
    }
  }

  /**
   * Retrieves the base destination directory from configuration.
   *
   * @return string|false
   *   The base directory path (e.g., 'public://exported_data') or FALSE if
   *   not configured.
   */
  private function getBaseDirectory() {
    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $base_directory = $config->get('destination_directory');

    if (empty($base_directory)) {
      $this->logger->error('El directorio de destino no está configurado, no se pueden borrar todos los archivos serializados.');
      return FALSE;
    }
    return $base_directory;
  }

  /**
   * Deletes the directory for a specific entity ID within its entity type.
   *
   * This means removing: [destination_directory]/[entity_type_id]/[entity_id]/
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The ID of the entity whose directory should be deleted.
   *
   * @return bool
   *   TRUE on successful deletion or if the directory does not exist,
   *   FALSE otherwise.
   */
  public function deleteEntityDirectory($entity_type_id, $entity_id) {
    $base_directory = $this->getBaseDirectory();
    if (!$base_directory) {
      return FALSE;
    }
    $target_directory = $this->getEntityDirectory($entity_type_id, $entity_id);
    try {
      $this->fileSystem->deleteRecursive($target_directory);
      return TRUE;
    }
    catch (\Throwable $th) {

      return FALSE;
    }
  }

  /**
   * Deletes a specific serialized entity file for a given language.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The ID of the entity.
   * @param string $language_id
   *   The language code of the file to delete.
   *
   * @return bool
   *   TRUE on successful deletion or if the file does not exist,
   *   FALSE otherwise.
   */
  public function deleteEntityFile($entity_type_id, $entity_id, $language_id) {
    $base_directory = $this->getBaseDirectory();
    if (!$base_directory) {
      return FALSE;
    }
    $target_directory = $this->getEntityDirectory($entity_type_id, $entity_id);

    $filepath = $target_directory . '/' . $language_id . '.json';
    try {
      $this->fileSystem->delete($filepath);
      return TRUE;
    }
    catch (\Throwable $th) {

      return FALSE;
    }
  }

}
