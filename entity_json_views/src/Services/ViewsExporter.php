<?php

declare(strict_types=1);

namespace Drupal\entity_json_views\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\views\Views;

/**
 * Exports configured REST export View displays as static JSON files.
 */
class ViewsExporter {

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new ViewsExporter.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('entity_json_views');
  }

  /**
   * Exports all configured View displays as JSON files.
   *
   * @return array[]
   *   An array of result arrays, each with keys: view_id, display_id,
   *   success, and optional error.
   */
  public function exportAll(): array {
    $config = $this->configFactory->get('entity_json_views.settings');
    $views = $config->get('views') ?? [];
    $results = [];

    foreach ($views as $item) {
      try {
        $this->exportView($item['view_id'], $item['display_id']);
        $results[] = [
          'view_id' => $item['view_id'],
          'display_id' => $item['display_id'],
          'success' => TRUE,
        ];
      }
      catch (\Throwable $e) {
        $this->logger->error('Failed to export view {view} display {display}: @msg', [
          'view' => $item['view_id'],
          'display' => $item['display_id'],
          '@msg' => $e->getMessage(),
        ]);
        $results[] = [
          'view_id' => $item['view_id'],
          'display_id' => $item['display_id'],
          'success' => FALSE,
          'error' => $e->getMessage(),
        ];
      }
    }

    return $results;
  }

  /**
   * Exports a single View display as a JSON file.
   *
   * @param string $view_id
   *   The view machine name.
   * @param string $display_id
   *   The display ID (e.g. 'rest_export_1').
   *
   * @return string
   *   The file URI where the JSON was saved.
   *
   * @throws \RuntimeException
   *   If the view is not found or does not return a JSON string.
   */
  public function exportView(string $view_id, string $display_id): string {
    $view = Views::getView($view_id);
    if (!$view) {
      throw new \RuntimeException(sprintf('View "%s" not found.', $view_id));
    }

    $view->setDisplay($display_id);
    $view->execute();

    $json = $view->style_plugin->render();
    if (!is_string($json)) {
      throw new \RuntimeException(sprintf(
        'View "%s" display "%s" did not return a JSON string.',
        $view_id,
        $display_id,
      ));
    }

    $uri = 'public://views/' . $view_id . '/' . $display_id . '.json';
    $directory = dirname($uri);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $this->fileSystem->saveData($json, $uri, FileSystemInterface::EXISTS_REPLACE);

    return $uri;
  }

}
