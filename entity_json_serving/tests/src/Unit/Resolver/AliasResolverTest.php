<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_json_serving\Unit\Resolver;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\entity_json_serving\Resolver\AliasResolver;
use Drupal\path_alias\AliasManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests the AliasResolver service.
 *
 * @group entity_json_serving
 */
class AliasResolverTest extends TestCase {

  /**
   * Creates an AliasResolver with mocked dependencies.
   */
  private function createResolver(callable $aliasCallback): AliasResolver {
    $aliasManager = $this->createMock(AliasManagerInterface::class);
    $aliasManager->method('getPathByAlias')->willReturnCallback($aliasCallback);

    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    return new AliasResolver($aliasManager, $loggerFactory);
  }

  /**
   * Tests resolving a simple node alias.
   */
  public function testResolveSimpleNodeAlias(): void {
    $resolver = $this->createResolver(fn(string $alias) => match ($alias) {
      '/about-us' => '/node/1',
      default => $alias,
    });

    $result = $resolver->resolve('about-us');
    $this->assertNotNull($result);
    $this->assertSame('node', $result['entity_type']);
    $this->assertSame('1', $result['entity_id']);
  }

  /**
   * Tests resolving a multi-segment alias.
   */
  public function testResolveMultiSegmentAlias(): void {
    $resolver = $this->createResolver(fn(string $alias) => match ($alias) {
      '/products/shoes' => '/node/42',
      default => $alias,
    });

    $result = $resolver->resolve('products/shoes');
    $this->assertNotNull($result);
    $this->assertSame('node', $result['entity_type']);
    $this->assertSame('42', $result['entity_id']);
  }

  /**
   * Tests resolving a user entity alias.
   */
  public function testResolveUserEntityAlias(): void {
    $resolver = $this->createResolver(fn(string $alias) => match ($alias) {
      '/my-profile' => '/user/5',
      default => $alias,
    });

    $result = $resolver->resolve('my-profile');
    $this->assertNotNull($result);
    $this->assertSame('user', $result['entity_type']);
    $this->assertSame('5', $result['entity_id']);
  }

  /**
   * Tests resolving with leading slash is normalized.
   */
  public function testResolveWithLeadingSlash(): void {
    $resolver = $this->createResolver(fn(string $alias) => match ($alias) {
      '/about-us' => '/node/1',
      default => $alias,
    });

    $result = $resolver->resolve('/about-us');
    $this->assertNotNull($result);
    $this->assertSame('node', $result['entity_type']);
    $this->assertSame('1', $result['entity_id']);
  }

  /**
   * Tests that non-existent alias returns NULL.
   */
  public function testResolveNonExistentAliasReturnsNull(): void {
    $resolver = $this->createResolver(fn(string $alias) => $alias);

    $result = $resolver->resolve('no-existe');
    $this->assertNull($result);
  }

  /**
   * Tests that a non-entity internal path returns NULL.
   */
  public function testResolveNonEntityPathReturnsNull(): void {
    $resolver = $this->createResolver(fn(string $alias) => match ($alias) {
      '/admin' => '/admin/config',
      default => $alias,
    });

    $result = $resolver->resolve('admin');
    $this->assertNull($result);
  }

}
