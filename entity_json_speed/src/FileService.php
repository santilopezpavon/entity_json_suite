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

  /**
   * Constructor.
   */
  public function __construct($fileSystem, $entityTypeManager) {
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
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
    dump($path);

    if (file_exists($path)) {
        $this->fileSystem->delete($path);
    } 
  }

  public function deleteAllFiles() {
    $this->fileSystem->deleteRecursive('public://json_exports/');
  }

  private function getPath($path) {
    $path = preg_replace('#/+#', '/', $path);
    return 'public://json_exports/' . $path;
  }





}
