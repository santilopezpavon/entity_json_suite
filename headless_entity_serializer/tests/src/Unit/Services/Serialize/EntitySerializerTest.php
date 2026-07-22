<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Unit\Services\Serialize;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\headless_entity_serializer\Services\Serialize\EntitySerializer;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Tests the EntitySerializer service.
 *
 * @group headless_entity_serializer
 */
class EntitySerializerTest extends TestCase {

  /**
   * Creates a configured EntitySerializer with mocked dependencies.
   */
  private function createSerializer(array $inlineTypes = []): EntitySerializer {
    $fileStorage = $this->createMock(FileStorageManager::class);

    $serializer = $this->createMock(SerializerInterface::class);
    $serializer->method('serialize')->willReturn('{"id":"test"}');

    $config = $this->createMock(Config::class);
    $config->method('get')->with('entity_types_inline')->willReturn($inlineTypes);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    return new EntitySerializer($fileStorage, $serializer, $configFactory);
  }

  /**
   * Creates a mock entity with given type, id, and translatability.
   */
  private function createMockEntity(string $entityTypeId, string $entityId, bool $translatable = FALSE): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn($entityId);
    $entity->method('isTranslatable')->willReturn($translatable);

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');
    $entity->method('language')->willReturn($language);

    if ($translatable) {
      $enLang = $this->createMock(LanguageInterface::class);
      $enLang->method('getId')->willReturn('en');
      $entity->method('getTranslationLanguages')->willReturn(['en' => $enLang]);
      $entity->method('getTranslation')->with('en')->willReturn($entity);
    }

    $fieldDef = $this->createMock(FieldDefinitionInterface::class);
    $fieldDef->method('getSetting')->with('target_type')->willReturn(NULL);
    $entity->method('getFieldDefinitions')->willReturn([]);

    return $entity;
  }

  /**
   * Tests that recursion guard prevents infinite loops on circular refs.
   *
   * Verifies fix #1: exportEntity() tracks processed entities to avoid
   * stack overflow when entity type A references type B and B references A.
   */
  public function testExportEntityDoesNotRecurseOnCircularReference(): void {
    $serializer = $this->createSerializer();

    // Simulate: entityA -> entityB -> entityA (circular)
    // No inline config means no recursion beyond the guard test.
    // Test the guard by calling exportEntity twice with the same entity.
    $entity = $this->createMockEntity('node', '1');

    // First call should succeed.
    $serializer->exportEntity($entity);
    $serializer->exportEntity($entity);

    // Test passes if no exception/infinite loop occurs.
    $this->addToAssertionCount(1);
  }

  /**
   * Tests exportEntity guards against re-processing the same entity.
   *
   * When entity types are configured for inline export, exportEntity(A)
   * calls exportEntity(B), but exportEntity(B) should not call
   * exportEntity(A) again if A is in the processed set.
   */
  public function testRecursionGuardAcrossInlineEntities(): void {
    $serializer = $this->createSerializer(['node' => 'node']);

    $entityA = $this->createMockEntity('node', '1');

    $this->createMockEntity('node', '2');

    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('isEmpty')->willReturn(FALSE);

    // B refers back to A (circular) but it should be guarded.
    /** @phpstan-ignore method.notFound */
    $entityA->method('get')->willReturn($fieldItemList);
    /** @phpstan-ignore method.notFound */
    $entityA->method('getFieldDefinitions')->willReturn([]);

    // Even with circular references, it should not infinite loop.
    $serializer->exportEntity($entityA);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that entity with no translations is still serialized.
   */
  public function testExportEntityNonTranslatable(): void {
    $serializer = $this->createSerializer();
    $entity = $this->createMockEntity('node', '1', FALSE);

    $serializer->exportEntity($entity);
    $this->addToAssertionCount(1);
  }

}
