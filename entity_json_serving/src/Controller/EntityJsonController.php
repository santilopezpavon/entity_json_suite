<?php

declare(strict_types=1);

namespace Drupal\entity_json_serving\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_json_serving\Resolver\AliasResolver;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for serving serialized entity JSON files via HTTP.
 */
class EntityJsonController implements ContainerInjectionInterface {

  /**
   * The file storage manager.
   */
  protected FileStorageManager $fileStorageManager;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The alias resolver.
   */
  protected AliasResolver $aliasResolver;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * The language manager service.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new EntityJsonController.
   */
  public function __construct(
    FileStorageManager $file_storage_manager,
    FileSystemInterface $file_system,
    AliasResolver $alias_resolver,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    LanguageManagerInterface $language_manager,
  ) {
    $this->fileStorageManager = $file_storage_manager;
    $this->fileSystem = $file_system;
    $this->aliasResolver = $alias_resolver;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('headless_entity_serializer.file_storage_manager'),
      $container->get('file_system'),
      $container->get('entity_json_serving.alias_resolver'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('entity_json_serving'),
      $container->get('language_manager'),
    );
  }

  /**
   * Serves a single entity translation JSON file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The language code.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with the JSON file content.
   */
  public function serveEntity(Request $request, string $entity_type, string $entity_id, string $langcode): Response {
    $uri = $this->fileStorageManager->getEntityFilePath($entity_type, $entity_id, $langcode);
    if ($uri === FALSE) {
      throw new NotFoundHttpException('Entity JSON file not found.');
    }

    $filepath = $this->fileSystem->realpath($uri);
    if ($filepath === FALSE || !is_file($filepath)) {
      throw new NotFoundHttpException('Entity JSON file not found.');
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
   * Serves an entity JSON file resolved from a path alias.
   *
   * Uses the negotiated interface language to determine the translation.
   * Falls back to the entity's default language if the translation is not
   * available.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $alias
   *   The path alias (e.g., 'about-us').
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response with the JSON file content.
   */
  public function serveByAlias(Request $request, string $alias): Response {
    // The alias may be URL-encoded by the path processor.
    // Multi-segment aliases with '/' are encoded to '%2F' so they
    // fit in a single route segment.
    $alias = urldecode($alias);

    $resolved = $this->aliasResolver->resolve($alias);

    if ($resolved === NULL) {
      throw new NotFoundHttpException('Alias not found.');
    }

    $storage = $this->entityTypeManager->getStorage($resolved['entity_type']);
    $entity = $storage->load($resolved['entity_id']);

    if (!$entity instanceof ContentEntityInterface) {
      throw new NotFoundHttpException('Referenced entity not found.');
    }

    // Determine the language to serve using the negotiated interface
    // language. If the entity has a matching translation, use it.
    // Otherwise fall back to the entity's default language.
    $interfaceLangcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();

    $langcode = $entity->hasTranslation($interfaceLangcode) ? $interfaceLangcode : $entity->language()->getId();

    return $this->serveEntity($request, $resolved['entity_type'], $resolved['entity_id'], $langcode);
  }

}
