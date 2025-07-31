<?php

namespace Drupal\headless_entity_serializer\Services\Alias;

use Drupal\Core\File\FileSystemInterface;

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
   * Constructs a new AliasBucketManager object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
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

      $directoryAlias = $base_directory . "/alias-buckets/" . $data->path[0]->langcode . "/" . $bucket;

      // CLEAN INIT.
      $filenameToSearch = $entity_type . '-' . $entity_id . '.json';
      $mask = '/' . preg_quote($filenameToSearch, '/') . '/';
      $files = $this->fileSystem->scanDirectory($directoryAlias, $mask, [
        'recurse' => TRUE,
      ]);
      foreach ($files as $key => $file) {
        $directoryParent = dirname($key);
        $this->fileSystem->deleteRecursive($directoryParent);
      }

      // CLEAN END.
      $directoryAlias .= $data->path[0]->alias;
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
   * This method reads a 'data.json' file, extracts the alias information,
   * and then recursively deletes the corresponding alias directory and
   * all its contents.
   *
   * @param string $file_path
   *   The URI of the 'data.json' file to read (e.g., 'public://.../alias/data.json').
   * @param string $base_directory
   *   The base directory URI for the alias buckets (e.g., 'public://').
   */
  public function removeAliasEntity($file_path, $base_directory) {
    try {
      $filepathAbsolute = $this->fileSystem->realpath($file_path);
      $json_content = file_get_contents($filepathAbsolute);

      $data = json_decode($json_content);
      if (isset($data->path[0]->pid)) {
        $bucket = floor($data->path[0]->pid / 1000);
        $directoryAlias = $base_directory . "/alias-buckets/" . $data->path[0]->langcode . "/" . $bucket;
        $directoryAlias .= $data->path[0]->alias;
        $this->fileSystem->deleteRecursive($directoryAlias);
      }
    }
    catch (\Throwable $th) {
      // It's generally better to log the exception rather than just catching it.
      // E.g., \Drupal::logger('my_module')->error($th->getMessage());
    }

  }

  /**
   * Removes all alias entities within a target directory.
   *
   * This method scans a given directory for JSON files and, for each one found,
   * calls `removeAliasEntity()` to delete the associated alias directory.
   *
   * @param string $target_directory
   *   The URI of the directory to scan for alias files (e.g., 'public://.../node/123/').
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
