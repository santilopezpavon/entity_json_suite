<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Services\Alias;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to manage alias buckets for headless consumption.
 *
 * This service is responsible for organizing and exporting path aliases
 * into a structured directory system based on 'buckets', making them
 * easily accessible for a headless application.
 */
class AliasBucketManager {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AliasBucketManager object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('headless_entity_serializer');
  }

  /**
   * Sanitizes an alias path for safe filesystem use.
   *
   * Strips path traversal sequences while preserving forward slashes
   * that represent the alias hierarchy.
   *
   * @param string $alias
   *   The raw alias path (e.g., '/about/us').
   *
   * @return string
   *   The sanitized alias path.
   */
  private function sanitizeAliasPath(string $alias): string {
    $alias = str_replace('\\', '/', $alias);
    $parts = explode('/', $alias);
    $safe = [];
    foreach ($parts as $part) {
      if (in_array($part, ['', '.', '..'], TRUE)) {
        continue;
      }
      $part = preg_replace('/[^a-zA-Z0-9\-_]/', '', $part);
      if ($part !== '') {
        $safe[] = $part;
      }
    }
    return '/' . implode('/', $safe);
  }

  /**
   * Generates and saves an alias entity to a specific bucket directory.
   *
   * This method takes JSON data, extracts the path alias ID (pid), and
   * organizes the alias into a bucket-based directory structure. It then
   * creates a 'data.json' file within this structure containing the
   * entity type and ID.
   *
   * @param string $json_data
   *   The JSON string containing the alias entity data.
   * @param string $entity_type
   *   The entity type ID (e.g., 'node', 'media').
   * @param string $entity_id
   *   The entity ID.
   * @param string $base_directory
   *   The base directory URI for storing the aliases (e.g., 'public://').
   */
  public function generateAlias($json_data, $entity_type, $entity_id, $base_directory) {
    $data = json_decode($json_data);

    if (isset($data->path[0]->pid)) {
      $bucket = floor($data->path[0]->pid / 1000);
      $safeLangcode = preg_replace('/[^a-z0-9\-_]/', '', $data->path[0]->langcode);
      $safeAlias = $this->sanitizeAliasPath($data->path[0]->alias);

      $directoryAlias = $base_directory . "/alias-buckets/" . $safeLangcode . "/" . $bucket;

      // Clean old alias files for this entity only if bucket dir exists.
      if (is_dir($directoryAlias)) {
        $filenameToSearch = $entity_type . '-' . $entity_id . '.json';
        $mask = '/' . preg_quote($filenameToSearch, '/') . '/';
        $files = $this->fileSystem->scanDirectory($directoryAlias, $mask, [
          'recurse' => TRUE,
        ]);
        foreach ($files as $key => $file) {
          $directoryParent = dirname((string) $key);
          $this->fileSystem->deleteRecursive($directoryParent);
        }
      }

      $directoryAlias .= $safeAlias;
      $this->fileSystem->prepareDirectory($directoryAlias, FileSystemInterface::CREATE_DIRECTORY);
      $dataAlias = [
        "entityType" => $entity_type,
        "entityId" => $entity_id,
      ];
      $pathFile = $directoryAlias . "/data.json";
      $pathFileMetadata = $directoryAlias . "/" . $entity_type . "-" . $entity_id . ".json";

      $dataAlias = json_encode($dataAlias);
      $this->fileSystem->saveData($dataAlias, $pathFile, FileSystemInterface::EXISTS_REPLACE);
      $this->fileSystem->saveData($dataAlias, $pathFileMetadata, FileSystemInterface::EXISTS_REPLACE);

    }
  }

  /**
   * Removes a single alias bucket directory.
   *
   * This method reads the entity's JSON file, extracts the alias information,
   * and then recursively deletes the corresponding alias directory and
   * all its contents.
   *
   * @param string $file_path
   *   The URI of the data file to read.
   * @param string $base_directory
   *   The base directory URI for the alias buckets (e.g., 'public://').
   */
  public function removeAliasEntity($file_path, $base_directory) {
    try {
      $json_content = file_get_contents($file_path);
      if ($json_content === FALSE) {
        $this->logger->warning('Could not read file for alias removal: {path}', [
          'path' => $file_path,
        ]);
        return;
      }

      $data = json_decode($json_content);
      if (isset($data->path[0]->pid)) {
        $bucket = floor($data->path[0]->pid / 1000);
        $safeLangcode = preg_replace('/[^a-z0-9\-_]/', '', $data->path[0]->langcode);
        $safeAlias = $this->sanitizeAliasPath($data->path[0]->alias);
        $directoryAlias = $base_directory
          . "/alias-buckets/" . $safeLangcode . "/" . $bucket
          . $safeAlias;
        $this->fileSystem->deleteRecursive($directoryAlias);
      }
    }
    catch (\Throwable $th) {
      $this->logger->error('Error removing alias for {path}: @message', [
        'path' => $file_path,
        '@message' => $th->getMessage(),
      ]);
    }

  }

  /**
   * Removes all alias entities within a target directory.
   *
   * This method scans a given directory for JSON files and, for each one found,
   * calls `removeAliasEntity()` to delete the associated alias directory.
   *
   * @param string $target_directory
   *   The URI of the directory to scan for alias files.
   * @param string $base_directory
   *   The base directory URI for the alias buckets (e.g., 'public://').
   */
  public function removeAliasEntities($target_directory, $base_directory) {
    $mask = '/\.json$/';
    $files = $this->fileSystem->scanDirectory($target_directory, $mask, [
      'recurse' => FALSE,
    ]);
    foreach ($files as $key => $value) {
      $this->removeAliasEntity($key, $base_directory);
    }
  }

}
