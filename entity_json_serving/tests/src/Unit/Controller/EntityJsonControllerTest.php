<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_json_serving\Unit\Controller;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_json_serving\Controller\EntityJsonController;
use Drupal\entity_json_serving\Resolver\AliasResolver;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the EntityJsonController.
 *
 * @group entity_json_serving
 */
class EntityJsonControllerTest extends TestCase {

  /**
   * The temporary test file path.
   */
  private string $tempFile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tempFile = tempnam(sys_get_temp_dir(), 'ejstest_');
    file_put_contents($this->tempFile, '{"test":true}');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->tempFile);
    parent::tearDown();
  }

  /**
   * Creates a mock language manager returning the given interface language.
   */
  private function createMockLanguageManager(string $interfaceLangcode = 'en'): LanguageManagerInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn($interfaceLangcode);

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_INTERFACE)
      ->willReturn($language);

    return $languageManager;
  }

  /**
   * Creates a controller with the given mocked dependencies.
   */
  private function createController(
    ?FileStorageManager $fileStorageManager = NULL,
    ?FileSystemInterface $fileSystem = NULL,
    ?AliasResolver $aliasResolver = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?LoggerInterface $logger = NULL,
    ?LanguageManagerInterface $languageManager = NULL,
  ): EntityJsonController {
    return new EntityJsonController(
      $fileStorageManager ?? $this->createMock(FileStorageManager::class),
      $fileSystem ?? $this->createMock(FileSystemInterface::class),
      $aliasResolver ?? $this->createMock(AliasResolver::class),
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
      $logger ?? $this->createMock(LoggerInterface::class),
      $languageManager ?? $this->createMockLanguageManager(),
    );
  }

  /**
   * Creates a controller configured to serve an entity file successfully.
   */
  private function createControllerForEntityServe(
    string $uri = 'public://test/node/1/en.json',
    ?string $realpath = NULL,
  ): EntityJsonController {
    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')->willReturn($uri);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($realpath ?? $this->tempFile);

    return $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
    );
  }

  /**
   * Tests serveEntity returns BinaryFileResponse when file exists.
   */
  public function testServeEntityReturnsBinaryFileResponse(): void {
    $controller = $this->createControllerForEntityServe();
    $request = new Request();

    $response = $controller->serveEntity($request, 'node', '1', 'en');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    $this->assertSame('application/json', $response->headers->get('Content-Type'));
  }

  /**
   * Tests serveEntity includes ETag and Last-Modified headers.
   */
  public function testServeEntityHasConditionalHeaders(): void {
    $controller = $this->createControllerForEntityServe();
    $response = $controller->serveEntity(new Request(), 'node', '1', 'en');

    $this->assertTrue($response->headers->has('ETag'));
    $this->assertTrue($response->headers->has('Last-Modified'));
  }

  /**
   * Tests serveEntity throws NotFoundHttpException when URI is FALSE.
   */
  public function testServeEntityThrowsNotFoundWhenUriFalse(): void {
    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')->willReturn(FALSE);

    $controller = $this->createController(fileStorageManager: $fileStorage);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Entity JSON file not found.');

    $controller->serveEntity(new Request(), 'node', '1', 'en');
  }

  /**
   * Tests serveEntity throws NotFoundHttpException when realpath is FALSE.
   */
  public function testServeEntityThrowsNotFoundWhenRealpathFalse(): void {
    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')->willReturn('public://test/node/1/en.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn(FALSE);

    $controller = $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
    );

    $this->expectException(NotFoundHttpException::class);

    $controller->serveEntity(new Request(), 'node', '1', 'en');
  }

  /**
   * Creates a mock content entity with a given language.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string $entityId
   *   The entity ID.
   * @param string $langcode
   *   The default language code.
   * @param string[] $translations
   *   Additional available translation langcodes beyond the default one.
   */
  private function createMockEntity(string $entityType, string $entityId, string $langcode, array $translations = []): ContentEntityInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn($langcode);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityType);
    $entity->method('id')->willReturn($entityId);
    $entity->method('language')->willReturn($language);
    $entity->method('hasTranslation')->willReturnCallback(
      fn(string $lang) => $lang === $langcode || in_array($lang, $translations, TRUE),
    );

    return $entity;
  }

  /**
   * Creates a storage mock that loads the given entity.
   */
  private function createStorageWithEntity(?ContentEntityInterface $entity): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($entity);
    return $storage;
  }

  /**
   * Tests serveByAlias resolves and serves entity using interface language.
   */
  public function testServeByAliasResolvesAndServes(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')
      ->with('about-us')
      ->willReturn(['entity_type' => 'node', 'entity_id' => '1']);

    $entity = $this->createMockEntity('node', '1', 'en');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')
      ->willReturn($this->createStorageWithEntity($entity));

    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')
      ->with('node', '1', 'en')
      ->willReturn('public://test/node/1/en.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($this->tempFile);

    $controller = $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
      aliasResolver: $aliasResolver,
      entityTypeManager: $entityTypeManager,
      languageManager: $this->createMockLanguageManager('en'),
    );

    $response = $controller->serveByAlias(new Request(), 'about-us');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests serveByAlias uses interface language when translation exists.
   */
  public function testServeByAliasUsesInterfaceLanguage(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')
      ->with('about-us')
      ->willReturn(['entity_type' => 'node', 'entity_id' => '1']);

    $entity = $this->createMockEntity('node', '1', 'en', ['es']);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')
      ->willReturn($this->createStorageWithEntity($entity));

    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')
      ->with('node', '1', 'es')
      ->willReturn('public://test/node/1/es.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($this->tempFile);

    $controller = $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
      aliasResolver: $aliasResolver,
      entityTypeManager: $entityTypeManager,
      languageManager: $this->createMockLanguageManager('es'),
    );

    $response = $controller->serveByAlias(new Request(), 'about-us');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests serveByAlias falls back to default language.
   *
   * When the interface language translation is not available on the entity,
   * the controller should serve the entity's default language instead.
   */
  public function testServeByAliasFallsBackToDefaultLanguage(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')
      ->with('about-us')
      ->willReturn(['entity_type' => 'node', 'entity_id' => '1']);

    $entity = $this->createMockEntity('node', '1', 'en');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')
      ->willReturn($this->createStorageWithEntity($entity));

    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')
      ->with('node', '1', 'en')
      ->willReturn('public://test/node/1/en.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($this->tempFile);

    $controller = $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
      aliasResolver: $aliasResolver,
      entityTypeManager: $entityTypeManager,
      languageManager: $this->createMockLanguageManager('fr'),
    );

    $response = $controller->serveByAlias(new Request(), 'about-us');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests serveByAlias with multi-segment alias (simulates decoded slash).
   */
  public function testServeByAliasMultiSegmentAlias(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')
      ->with('hola-mundo/3')
      ->willReturn(['entity_type' => 'node', 'entity_id' => '2']);

    $entity = $this->createMockEntity('node', '2', 'en');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')
      ->willReturn($this->createStorageWithEntity($entity));

    $fileStorage = $this->createMock(FileStorageManager::class);
    $fileStorage->method('getEntityFilePath')
      ->with('node', '2', 'en')
      ->willReturn('public://test/node/0/2/en.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($this->tempFile);

    $controller = $this->createController(
      fileStorageManager: $fileStorage,
      fileSystem: $fileSystem,
      aliasResolver: $aliasResolver,
      entityTypeManager: $entityTypeManager,
    );

    // The alias 'hola-mundo%2F3' comes URL-encoded from the route,
    // urldecode() in serveByAlias turns it into 'hola-mundo/3'.
    $response = $controller->serveByAlias(new Request(), 'hola-mundo%2F3');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests serveByAlias throws 404 when alias not resolved.
   */
  public function testServeByAliasThrowsNotFoundWhenAliasUnknown(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')->willReturn(NULL);

    $controller = $this->createController(aliasResolver: $aliasResolver);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Alias not found.');

    $controller->serveByAlias(new Request(), 'no-existe');
  }

  /**
   * Tests serveByAlias throws 404 when entity not found.
   */
  public function testServeByAliasThrowsNotFoundWhenEntityMissing(): void {
    $aliasResolver = $this->createMock(AliasResolver::class);
    $aliasResolver->method('resolve')
      ->willReturn(['entity_type' => 'node', 'entity_id' => '999']);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')
      ->willReturn($this->createStorageWithEntity(NULL));

    $controller = $this->createController(
      aliasResolver: $aliasResolver,
      entityTypeManager: $entityTypeManager,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Referenced entity not found.');

    $controller->serveByAlias(new Request(), 'ghost-entity');
  }

}
