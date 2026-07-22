<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Unit\Services\Storage;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\headless_entity_serializer\Services\Alias\AliasBucketManager;
use Drupal\headless_entity_serializer\Services\Storage\FileStorageManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FileStorageManager service.
 *
 * @group headless_entity_serializer
 */
class FileStorageManagerTest extends TestCase {

  /**
   * Tests that getBaseDirectory caches the result.
   *
   * Verifies fix #14: calling getBaseDirectory multiple times should only
   * read from config once.
   */
  public function testGetBaseDirectoryCachesResult(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())
      ->method('get')
      ->with('destination_directory')
      ->willReturn('public://test');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $aliasManager = $this->createMock(AliasBucketManager::class);

    $manager = new FileStorageManager(
      $fileSystem,
      $configFactory,
      $loggerFactory,
      $languageManager,
      $aliasManager,
    );

    // Access via reflection.
    $method = new \ReflectionMethod(FileStorageManager::class, 'getBaseDirectory');

    $first = $method->invoke($manager);
    $second = $method->invoke($manager);
    $third = $method->invoke($manager);

    $this->assertSame('public://test', $first);
    $this->assertSame($first, $second);
    $this->assertSame($first, $third);
  }

  /**
   * Tests deleteAllSerializedFiles rejects non-trusted schemes.
   *
   * Verifies fix #4: deleteAllSerializedFiles() should refuse to delete
   * directories that are not within a trusted stream wrapper (public, private,
   * temporary).
   */
  public function testDeleteAllSerializedFilesRejectsUntrustedScheme(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->with('destination_directory')->willReturn('/var/www/evil');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('error');

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects($this->never())->method('deleteRecursive');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $aliasManager = $this->createMock(AliasBucketManager::class);

    $manager = new FileStorageManager(
      $fileSystem,
      $configFactory,
      $loggerFactory,
      $languageManager,
      $aliasManager,
    );

    $result = $manager->deleteAllSerializedFiles();
    $this->assertFalse($result);
  }

  /**
   * Tests deleteAllSerializedFiles allows trusted public scheme.
   *
   * Verifies fix #4: trusted schemes (public, private, temporary) are allowed.
   */
  public function testDeleteAllSerializedFilesAllowsTrustedScheme(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->with('destination_directory')->willReturn('public://exported');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects($this->once())->method('deleteRecursive');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $aliasManager = $this->createMock(AliasBucketManager::class);

    $manager = new FileStorageManager(
      $fileSystem,
      $configFactory,
      $loggerFactory,
      $languageManager,
      $aliasManager,
    );

    $result = $manager->deleteAllSerializedFiles();
    $this->assertTrue($result);
  }

  /**
   * Tests saveData returns FALSE when base directory is invalid.
   *
   * Verifies fix #10: saveData() must check if getEntityDirectory()
   * returns FALSE before concatenating paths.
   */
  public function testSaveDataReturnsFalseOnInvalidDirectory(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->with('destination_directory')->willReturn('');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $logger = $this->createMock(LoggerChannelInterface::class);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $fileSystem = $this->createMock(FileSystemInterface::class);

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $aliasManager = $this->createMock(AliasBucketManager::class);

    $manager = new FileStorageManager(
      $fileSystem,
      $configFactory,
      $loggerFactory,
      $languageManager,
      $aliasManager,
    );

    $result = $manager->saveData('{"test":true}', '1', 'node', 'en');
    $this->assertFalse($result);
  }

}
