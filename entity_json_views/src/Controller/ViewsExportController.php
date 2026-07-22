<?php

declare(strict_types=1);

namespace Drupal\entity_json_views\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves exported View JSON files via HTTP.
 */
class ViewsExportController implements ContainerInjectionInterface {

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new ViewsExportController.
   */
  public function __construct(
    FileSystemInterface $file_system,
    LoggerInterface $logger,
  ) {
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('logger.factory')->get('entity_json_views'),
    );
  }

  /**
   * Serves a single exported View JSON file.
   */
  public function serveView(Request $request, string $view_id, string $display_id): Response {
    $uri = $this->getFileUri($view_id, $display_id);

    $filepath = $this->fileSystem->realpath($uri);
    if ($filepath === FALSE || !is_file($filepath)) {
      throw new NotFoundHttpException('View JSON file not found.');
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

  /**
   * Returns the file URI for a given view and display.
   */
  public function getFileUri(string $view_id, string $display_id): string {
    return 'public://views/' . $view_id . '/' . $display_id . '.json';
  }

}
