<?php

namespace Drupal\entity_json_speed;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;

/**
 * Service to handle JSON export and deletion for entities.
 */
class FileService {

  protected $fileSystem;
  protected $entityTypeManager;
  protected $files_dir;

  /**
   * Constructor.
   */
  public function __construct($fileSystem, $entityTypeManager) {
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
    $this->files_dir = 'public://json_exports/';
  }

  public function saveData($path, $data, $replace_file = TRUE) {
    $path = $this->getPath($path);
    $directory = dirname($path);

    if($replace_file === TRUE || !file_exists($path)) {
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->saveData($data, $path, FileSystemInterface::EXISTS_REPLACE);
    }
  }

  public function deleteFile($path) {
    $path = $this->getPath($path);

    if (file_exists($path)) {
        $this->fileSystem->delete($path);
    } 
  }

  public function deleteAllFiles() {
    $this->fileSystem->deleteRecursive($this->files_dir);
  }

  private function getPath($path) {
    $path = preg_replace('#/+#', '/', $path);
    return $this->files_dir . $path;
  }

  public function getPathFileEntity($entity) {
    $paths = $this->getPathsFilesEntity($entity);
    return $paths["path_file_current"];
  }
  public function getPathFileEntityConfig($entity) {
    $paths = $this->getPathsFilesEntity($entity);
    return $paths["path_file_config"];
  }


  public function getPathsFilesEntity($entity) {
    $langcode = 'neutral';
    try {
        $langcode = $entity->language()->getId();
    } catch (\Throwable $th) {}  

    $directory = "/" . $entity->getEntityTypeId() . '/' . $entity->bundle();
    $path_file_current =  $directory . '/' . $entity->id() . "/" . $langcode . '.json'; 
    $path_file_config =  $directory . '/' . $entity->id() . "/" . 'config' . '.json'; 

    return [
      "path_file_current" => $path_file_current,
      "path_file_config" => $path_file_config
    ];  
  }





}
