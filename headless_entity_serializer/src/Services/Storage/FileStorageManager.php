<?php

namespace Drupal\headless_entity_serializer\Services\Storage;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface; // Para getDestinationFilePath y t()

/**
 * Service to manage file system operations for serialized entities.
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
   * The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * The logger factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * The language manager.
   */
  public function __construct(
    FileSystemInterface $file_system,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    LanguageManagerInterface $language_manager
  ) {
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    $this->languageManager = $language_manager;
  }  

  

  public function createDirectory() {
    $directory = $this->getBaseDirectory();
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  }

  public function saveData($json_data, $entityId, $entity_type_id, $language_id) {
    $directory = $this->getBaseDirectory();
    $directory = $directory . "/" . $entity_type_id . "/" . $entityId . "/";
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $path = $directory . "" . $language_id . ".json";
    $this->fileSystem->saveData($json_data, $path, FileSystemInterface::EXISTS_REPLACE);

  }

  public function getEntitiesInFiles($entity_type_id) {
    $directory = $this->getBaseDirectory() . "/" . $entity_type_id;

    $files = $this->fileSystem->scanDirectory($directory, '/.*/');
    $resultado = [];

    foreach ($files as $uri => $file_info) {
      if (preg_match('#' . $entity_type_id . '/(\d+)/(\w+)\.json$#', $uri, $coincidencias)) {
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
    return $resultado;
  }

   /**
   * Deletes the entire base directory where serialized files are stored.
   * This effectively removes all serialized entity files.
   *
   * @return bool
   * TRUE on successful deletion or if directory does not exist, FALSE otherwise.
   */
  public function deleteAllSerializedFiles(): bool {
    try {
        $base_directory = $this->getBaseDirectory();
        $this->fileSystem->deleteRecursive($base_directory . "/");
        return TRUE;
    } catch (\Throwable $th) {
        return FALSE;
    }    
  }

  private function getBaseDirectory() {
    $config = $this->configFactory->get('headless_entity_serializer.settings');
    $base_directory = $config->get('destination_directory');

    if (empty($base_directory)) {
      $this->logger->error('El directorio de destino no está configurado, no se pueden borrar todos los archivos serializados.');
      return FALSE;
    }
    return $base_directory;
  }

  public function deleteEntityDirectory($entity_type_id, $entity_id) {
    $base_directory = $this->getBaseDirectory();
    if (!$base_directory) {
      return FALSE;
    }  
    $target_directory = $base_directory . '/' . $entity_type_id . '/' . $entity_id;
    dump($target_directory);
    try {
      $this->fileSystem->deleteRecursive($target_directory);
      return TRUE;
    } catch (\Throwable $th) {
      
      return FALSE;
    }
  }
  

  public function deleteEntityFile($entity_type_id, $entity_id, $language_id) {
    $base_directory = $this->getBaseDirectory();
    if (!$base_directory) {
      return FALSE;
    }
  
    $filepath = $base_directory . '/' . $entity_type_id . '/' . $entity_id . '/' . $language_id . '.json';
    try {
      $this->fileSystem->delete($filepath);
      return TRUE;
    } catch (\Throwable $th) {

      return FALSE;
    }
  }
  
}