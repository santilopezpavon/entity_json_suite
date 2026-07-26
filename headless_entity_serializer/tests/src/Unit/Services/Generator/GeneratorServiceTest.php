<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Unit\Services\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\headless_entity_serializer\Services\Generator\GeneratorService;
use Drupal\headless_entity_serializer\Services\Serialize\EntitySerializer;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the GeneratorService, specifically removeFileNotInDataBase().
 *
 * These tests verify that entity queries behave correctly for both
 * revisionable (e.g. node) and non-revisionable (e.g. ECK, paragraphs)
 * entity types, without requiring any specific contrib module to be installed.
 *
 * @group headless_entity_serializer
 */
class GeneratorServiceTest extends TestCase {

  /**
   * Creates a mock entity query with chainable methods.
   *
   * @param array $entityIds
   *   The entity IDs to return from execute().
   *
   * @return object
   *   A mock entity query object.
   */
  private function createQueryMock(array $entityIds = []): object {
    $query = new class($entityIds) {

      /** @var array */
      private $entityIds;

      /** @var bool */
      public $latestRevisionCalled = FALSE;

      /**
       * Constructor.
       *
       * @param array $entityIds
       *   Entity IDs to return.
       */
      public function __construct(array $entityIds) {
        $this->entityIds = $entityIds;
      }

      /**
       * @param bool $accessCheck
       *   Access check flag.
       *
       * @return $this
       *   Return self for chaining.
       */
      public function accessCheck(bool $accessCheck): static {
        return $this;
      }

      /**
       * @return $this
       *   Return self for chaining.
       */
      public function latestRevision(): static {
        $this->latestRevisionCalled = TRUE;
        return $this;
      }

      /**
       * Executes the query.
       *
       * @return array
       *   The entity IDs.
       */
      public function execute(): array {
        return $this->entityIds;
      }

    };

    return $query;
  }

  /**
   * Creates a GeneratorService with mocked dependencies.
   *
   * @param object|null $query
   *   Optional entity query mock to inject.
   * @param \Drupal\Core\Entity\EntityTypeInterface|null $entityType
   *   Optional entity type mock.
   * @param bool $isRevisionable
   *   Whether the entity type is revisionable.
   *
   * @return array
   *   An array with [GeneratorService, query mock].
   */
  private function createService(
    ?object $query = NULL,
    ?EntityTypeInterface $entityType = NULL,
    bool $isRevisionable = FALSE,
  ): array {
    if ($query === NULL) {
      $query = $this->createQueryMock([]);
    }

    if ($entityType === NULL) {
      $entityType = $this->createMock(EntityTypeInterface::class);
      $entityType->method('isRevisionable')->willReturn($isRevisionable);
    }

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('getEntityType')->willReturn($entityType);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $fileStorageManager = $this->createMock(FileStorageManager::class);
    $fileStorageManager->method('getEntitiesInFiles')->willReturn([]);

    $entitySerializer = $this->createMock(EntitySerializer::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $state = $this->createMock(StateInterface::class);

    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $languageManager = $this->createMock(LanguageManagerInterface::class);

    $database = $this->createMock(Connection::class);

    $service = new GeneratorService(
      $fileStorageManager,
      $configFactory,
      $entitySerializer,
      $state,
      $loggerFactory,
      $entityTypeManager,
      $languageManager,
      $database,
    );

    return [$service, $query, $storage, $fileStorageManager];
  }

  /**
   * Tests that latestRevision() is NOT called for non-revisionable entities.
   */
  public function testLatestRevisionNotCalledForNonRevisionable(): void {
    $query = $this->createQueryMock([1, 2, 3]);
    [$service] = $this->createService($query, NULL, FALSE);

    // Create a minimal storage mock for the actual call.
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(FALSE);
    $storage->method('getEntityType')->willReturn($entityType);
    $storage->method('loadMultiple')->willReturn([]);

    $service->removeFileNotInDataBase($storage, 'eck_type');

    $this->assertFalse(
      $query->latestRevisionCalled,
      'latestRevision() must NOT be called for non-revisionable entity types (e.g. ECK, paragraphs).'
    );
  }

  /**
   * Tests that latestRevision() IS called for revisionable entities.
   */
  public function testLatestRevisionCalledForRevisionable(): void {
    $query = $this->createQueryMock([1, 2, 3]);
    [$service] = $this->createService($query, NULL, TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(TRUE);
    $storage->method('getEntityType')->willReturn($entityType);
    $storage->method('loadMultiple')->willReturn([]);

    $service->removeFileNotInDataBase($storage, 'node');

    $this->assertTrue(
      $query->latestRevisionCalled,
      'latestRevision() MUST be called for revisionable entity types (e.g. node).'
    );
  }

  /**
   * Tests that orphaned entity directories are deleted from disk.
   */
  public function testOrphanedEntitiesDeletedFromDisk(): void {
    // DB has entity IDs [1, 2], but disk also has entity ID [3] (orphan).
    $query = $this->createQueryMock([1, 2]);

    $fileStorageManager = $this->createMock(FileStorageManager::class);
    $fileStorageManager->method('getEntitiesInFiles')->willReturn([
      '1' => ['en'],
      '2' => ['en'],
      '3' => ['en'],
    ]);
    $fileStorageManager->expects($this->once())
      ->method('deleteEntityDirectory')
      ->with('node', '3');

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('getEntityType')->willReturn($entityType);
    $storage->method('loadMultiple')->willReturn([]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $state = $this->createMock(StateInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $database = $this->createMock(Connection::class);

    $service = new GeneratorService(
      $fileStorageManager,
      $configFactory,
      $this->createMock(EntitySerializer::class),
      $state,
      $loggerFactory,
      $entityTypeManager,
      $languageManager,
      $database,
    );

    $service->removeFileNotInDataBase($storage, 'node');
  }

  /**
   * Tests that orphaned translation files are deleted.
   */
  public function testOrphanedTranslationsDeleted(): void {
    $query = $this->createQueryMock([1]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasTranslation')->willReturnMap([
      ['en', TRUE],
      ['es', FALSE],
    ]);

    $fileStorageManager = $this->createMock(FileStorageManager::class);
    $fileStorageManager->method('getEntitiesInFiles')->willReturn([
      '1' => ['en', 'es'],
    ]);
    $fileStorageManager->expects($this->once())
      ->method('deleteEntityFile')
      ->with('node', '1', 'es');

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('getEntityType')->willReturn($entityType);
    $storage->method('loadMultiple')->willReturn([1 => $entity]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $state = $this->createMock(StateInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $database = $this->createMock(Connection::class);

    $service = new GeneratorService(
      $fileStorageManager,
      $configFactory,
      $this->createMock(EntitySerializer::class),
      $state,
      $loggerFactory,
      $entityTypeManager,
      $languageManager,
      $database,
    );

    $service->removeFileNotInDataBase($storage, 'node');
  }

  /**
   * Tests that no file operations occur when no orphaned files exist.
   */
  public function testNoFileOperationsWhenNoOrphans(): void {
    $query = $this->createQueryMock([1, 2]);

    $entity1 = $this->createMock(ContentEntityInterface::class);
    $entity1->method('hasTranslation')->willReturnMap([
      ['en', TRUE],
      ['es', TRUE],
    ]);

    $entity2 = $this->createMock(ContentEntityInterface::class);
    $entity2->method('hasTranslation')->willReturnMap([
      ['en', TRUE],
    ]);

    $fileStorageManager = $this->createMock(FileStorageManager::class);
    $fileStorageManager->method('getEntitiesInFiles')->willReturn([
      '1' => ['en', 'es'],
      '2' => ['en'],
    ]);
    $fileStorageManager->expects($this->never())->method('deleteEntityDirectory');
    $fileStorageManager->expects($this->never())->method('deleteEntityFile');

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('getEntityType')->willReturn($entityType);
    $storage->method('loadMultiple')->willReturn([1 => $entity1, 2 => $entity2]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $state = $this->createMock(StateInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $database = $this->createMock(Connection::class);

    $service = new GeneratorService(
      $fileStorageManager,
      $configFactory,
      $this->createMock(EntitySerializer::class),
      $state,
      $loggerFactory,
      $entityTypeManager,
      $languageManager,
      $database,
    );

    $result = $service->removeFileNotInDataBase($storage, 'node');

    $this->assertTrue($result['status']);
  }

}
