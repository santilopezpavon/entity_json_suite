<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Unit\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\headless_entity_serializer\Services\EntityTrackingService;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EntityTrackingService.
 *
 * @group headless_entity_serializer
 */
class EntityTrackingServiceTest extends TestCase {

  /**
   * Creates a mock merge builder with chainable methods.
   */
  private function createMergeMock(Connection &$database): object {
    /** @var array $keysArg */
    $merge = new class {

      /** @var array */
      public $keysArg;

      /** @var array */
      public $fieldsArg;

      /**
       * @param array $keys
       *   The merge keys.
       *
       * @return $this
       *   Return self for chaining.
       */
      public function keys(array $keys): static {
        $this->keysArg = $keys;
        return $this;
      }

      /**
       * @param array $fields
       *   The merge fields.
       *
       * @return $this
       *   Return self for chaining.
       */
      public function fields(array $fields): static {
        $this->fieldsArg = $fields;
        return $this;
      }

      /**
       * Executes the merge query.
       *
       * @return int
       *   The number of affected rows.
       */
      public function execute(): int {
        return 1;
      }

    };

    $database->method('merge')->willReturn($merge);
    return $merge;
  }

  /**
   * Tests that entity_types entities are tracked.
   */
  public function testTrackChangeForEntityType(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('123');

    $database = $this->createMock(Connection::class);
    $merge = $this->createMergeMock($database);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $service = new EntityTrackingService($database, $configFactory);
    $service->trackChange($entity, 'insert');

    $this->assertEquals('node', $merge->keysArg['entity_type_id']);
    $this->assertEquals('123', $merge->keysArg['entity_id']);
    $this->assertEquals('insert', $merge->fieldsArg['operation']);
    $this->assertIsInt($merge->fieldsArg['changed']);
  }

  /**
   * Tests that entity_types_inline entities are also tracked.
   */
  public function testTrackChangeForInlineEntityType(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('paragraph');
    $entity->method('id')->willReturn('456');

    $database = $this->createMock(Connection::class);
    $merge = $this->createMergeMock($database);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', ['paragraph']],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $service = new EntityTrackingService($database, $configFactory);
    $service->trackChange($entity, 'insert');

    $this->assertEquals('paragraph', $merge->keysArg['entity_type_id']);
    $this->assertEquals('insert', $merge->fieldsArg['operation']);
  }

  /**
   * Tests that unconfigured entity types are NOT tracked.
   */
  public function testUnconfiguredEntityNotTracked(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('custom_entity');
    $entity->method('id')->willReturn('789');

    $database = $this->createMock(Connection::class);
    $database->expects($this->never())->method('merge');

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', ['paragraph']],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $service = new EntityTrackingService($database, $configFactory);
    $service->trackChange($entity, 'insert');
  }

  /**
   * Tests that entities with NULL id are not tracked.
   */
  public function testEntityWithNullIdNotTracked(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(NULL);

    $database = $this->createMock(Connection::class);
    $database->expects($this->never())->method('merge');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);

    $service = new EntityTrackingService($database, $configFactory);
    $service->trackChange($entity, 'insert');
  }

  /**
   * Tests that revision_id is captured for revisionable entities.
   */
  public function testRevisionIdCapturedForRevisionableEntity(): void {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(TRUE);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('100');
    $entity->method('getEntityType')->willReturn($entityType);
    $entity->method('getLoadedRevisionId')->willReturn(55);

    $database = $this->createMock(Connection::class);
    $merge = $this->createMergeMock($database);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node']],
      ['entity_types_inline', []],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $service = new EntityTrackingService($database, $configFactory);
    $service->trackChange($entity, 'insert');

    $this->assertEquals(55, $merge->fieldsArg['revision_id']);
  }

  /**
   * Tests that both entity_types and entity_types_inline are checked.
   */
  public function testBothConfigsMergedForTracking(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['entity_types', ['node', 'taxonomy_term']],
      ['entity_types_inline', ['paragraph', 'file']],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $database = $this->createMock(Connection::class);

    $service = new EntityTrackingService($database, $configFactory);

    $this->assertTrue($service->isTrackedEntityType('node'));
    $this->assertTrue($service->isTrackedEntityType('taxonomy_term'));
    $this->assertTrue($service->isTrackedEntityType('paragraph'));
    $this->assertTrue($service->isTrackedEntityType('file'));
    $this->assertFalse($service->isTrackedEntityType('custom_entity'));
  }

}
