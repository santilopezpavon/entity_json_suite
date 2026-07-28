<?php

declare(strict_types=1);

namespace Drupal\entity_json_menu\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\entity_json_menu\Services\MenuExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves exported Menu JSON files via HTTP.
 */
class MenuExportController implements ContainerInjectionInterface {

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The menu exporter service.
   */
  protected MenuExporter $menuExporter;

  /**
   * Constructs a new MenuExportController.
   */
  public function __construct(
    FileSystemInterface $file_system,
    MenuExporter $menu_exporter,
  ) {
    $this->fileSystem = $file_system;
    $this->menuExporter = $menu_exporter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('entity_json_menu.exporter'),
    );
  }

  /**
   * Serves a single exported Menu JSON file.
   */
  public function serveMenu(Request $request, string $menu_id): Response {
    $uri = $this->menuExporter->getFileUri($menu_id);

    $filepath = $this->fileSystem->realpath($uri);
    if ($filepath === FALSE || !is_file($filepath)) {
      throw new NotFoundHttpException('Menu JSON file not found.');
    }

    $response = new BinaryFileResponse(
      $filepath,
      200,
      ['Content-Type' => 'application/json'],
      TRUE,
      NULL,
      TRUE,
      TRUE,
    );

    $response->headers->set('Cache-Control', 'public, max-age=3600');

    if ($response->isNotModified($request)) {
      return $response;
    }

    return $response;
  }

}
