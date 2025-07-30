<?php

namespace Drupal\headless_entity_serializer\Services\Alias;

use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;

/**
 * Service to manage and export path aliases for headless consumption.
 */
class AliasManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file storage manager service.
   *
   * @var \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager
   */
  protected $fileStorageManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AliasManager object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   (Opcional) El gestor de idiomas, si se necesita lógica adicional de idiomas.
   */
  public function __construct(
    Connection $database,
    FileStorageManager $file_storage_manager,
    LoggerChannelFactoryInterface $logger_factory,
    // Inyectado por si se necesita en el futuro.
    LanguageManagerInterface $language_manager,
  ) {
    $this->database = $database;
    $this->fileStorageManager = $file_storage_manager;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    // $this->languageManager = $language_manager; // Si no lo usas, puedes eliminarlo.
  }

  /**
   * Fetches all path aliases from the database and organizes them by language.
   *
   * @return array
   *   An associative array where keys are language codes and values are
   *   arrays of alias => path mappings.
   *   Example: ['en' => ['/alias1' => 'node-1', '/alias2' => 'node-2']]
   */
  public function getFormattedAliases(): array {
    $aliases = $this->database->select('path_alias', 'pa')
      ->fields('pa', ['path', 'alias', 'langcode'])
      ->execute()
      ->fetchAll();

    $result = [];

    foreach ($aliases as $aliasEntity) {
      $langcode = $aliasEntity->langcode;
      $path = $aliasEntity->path;
      $alias = $aliasEntity->alias;

      if (!isset($result[$langcode])) {
        $result[$langcode] = [];
      }

      $formattedPath = str_replace("/", "-", $path);
      if (!empty($formattedPath) && $formattedPath[0] === '-') {
        $formattedPath = substr($formattedPath, 1);
      }

      // El alias es la clave, y el path formateado es el valor.
      $result[$langcode][$alias] = $formattedPath;
    }

    return $result;
  }

  /**
   * Generates and saves all path aliases to files.
   *
   * @return array
   *   An associative array with 'status' (bool) and 'message' (string).
   */
  public function generateAndSaveAliases(): array {
    try {
      $formattedAliases = $this->getFormattedAliases();

      if (empty($formattedAliases)) {
        $this->logger->info('No se encontraron alias de ruta para exportar.');
        return [
          "status" => TRUE,
          "message" => "No path aliases found to export.",
        ];
      }

      foreach ($formattedAliases as $langcode => $aliasData) {
        $jsonData = json_encode($aliasData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonData === FALSE) {
          $this->logger->error('Error al codificar JSON para los alias del idioma {langcode}.', ['{langcode}' => $langcode]);
          throw new \RuntimeException(sprintf('Error al codificar JSON para los alias del idioma %s.', $langcode));
        }
        
        // Delegar el guardado al FileStorageManager.
        $this->fileStorageManager->saveData($jsonData, "alias", "alias", $langcode);
      }

      $this->logger->info('Generación de alias completada exitosamente.');
      return [
        "status" => TRUE,
        "message" => "The alias generation was successful.",
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Error durante la generación o guardado de alias: @message', ['@message' => $e->getMessage()]);
      return [
        "status" => FALSE,
        "message" => "An error occurred during alias generation: " . $e->getMessage(),
      ];
    }
  }

}
