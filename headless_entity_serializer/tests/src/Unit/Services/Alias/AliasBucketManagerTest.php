<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Unit\Services\Alias;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\headless_entity_serializer\Services\Alias\AliasBucketManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the AliasBucketManager service.
 *
 * @group headless_entity_serializer
 */
class AliasBucketManagerTest extends TestCase {

  /**
   * Data provider for sanitizeAliasPath.
   */
  public static function providerSanitizeAliasPath(): array {
    return [
      'simple path' => ['/about/us', '/about/us'],
      'path traversal with double dot' => ['../../etc/passwd', '/etc/passwd'],
      'path traversal with single dot' => ['././etc/passwd', '/etc/passwd'],
      'empty segments' => ['/about//us/', '/about/us'],
      'root alias' => ['/', '/'],
      'alias with special chars' => ['/test<script>alert/foo', '/testscriptalert/foo'],
      'windows backslash traversal' => ['..\\..\\windows', '/windows'],
      'only dots' => ['..', '/'],
      'only slash' => ['/', '/'],
      'dash and underscore preserved' => ['/my-page/about_us', '/my-page/about_us'],
      'mixed safe and unsafe' => ['/about/../contact/./form', '/about/contact/form'],
    ];
  }

  /**
   * Creates a bare instance for reflection-based testing.
   */
  private function getMockInstance(): AliasBucketManager {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $loggerChannel = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);

    return new AliasBucketManager($fileSystem, $loggerFactory);
  }

  /**
   * Tests sanitizeAliasPath with various inputs.
   *
   * @dataProvider providerSanitizeAliasPath
   */
  public function testSanitizeAliasPath(string $input, string $expected): void {
    $instance = $this->getMockInstance();
    $method = new \ReflectionMethod(AliasBucketManager::class, 'sanitizeAliasPath');
    $result = $method->invoke($instance, $input);
    $this->assertSame($expected, $result);
  }

  /**
   * Tests that sanitizeAliasPath never produces traversal sequences.
   */
  public function testSanitizeNeverProducesTraversal(): void {
    $instance = $this->getMockInstance();
    $method = new \ReflectionMethod(AliasBucketManager::class, 'sanitizeAliasPath');

    $malicious = [
      '/../../../../../../root',
      '/..%2F..%2Fetc',
      '/././././.',
    ];

    foreach ($malicious as $input) {
      $result = $method->invoke($instance, $input);
      $this->assertStringNotContainsString('..', $result);
      $this->assertStringNotContainsString('\\', $result);
    }
  }

}
