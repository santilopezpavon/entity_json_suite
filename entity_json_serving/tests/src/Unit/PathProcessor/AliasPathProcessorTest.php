<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_json_serving\Unit\PathProcessor;

use Drupal\entity_json_serving\PathProcessor\AliasPathProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the AliasPathProcessor inbound path processor.
 *
 * @group entity_json_serving
 */
class AliasPathProcessorTest extends TestCase {

  /**
   * Data provider for testProcessInbound.
   */
  public static function providerProcessInbound(): array {
    return [
      'simple alias' => [
        '/json/path/about-us',
        '/json/path/about-us',
      ],
      'multi-segment alias with slash' => [
        '/json/path/hola-mundo/3',
        '/json/path/hola-mundo%2F3',
      ],
      'triple segment alias' => [
        '/json/path/a/b/c',
        '/json/path/a%2Fb%2Fc',
      ],
      'deep alias with many slashes' => [
        '/json/path/1/2/3/4',
        '/json/path/1%2F2%2F3%2F4',
      ],
      'empty suffix with trailing slash' => [
        '/json/path/',
        '/json/path/',
      ],
      'non-matching prefix' => [
        '/other/path/about',
        '/other/path/about',
      ],
      'only the prefix no trailing slash' => [
        '/json/path',
        '/json/path',
      ],
      'already URL-encoded (no double-encode)' => [
        '/json/path/hola%2Fmundo',
        '/json/path/hola%2Fmundo',
      ],
      'alias with query string characters' => [
        '/json/path/page?id=1',
        '/json/path/page?id=1',
      ],
      'slash at start of suffix' => [
        '/json/path//rest',
        '/json/path/%2Frest',
      ],
      'single segment alias' => [
        '/json/path/contact',
        '/json/path/contact',
      ],
    ];
  }

  /**
   * Tests processInbound with various path inputs.
   *
   * @dataProvider providerProcessInbound
   */
  public function testProcessInbound(string $input, string $expected): void {
    $processor = new AliasPathProcessor();
    $request = $this->createMock(Request::class);
    $result = $processor->processInbound($input, $request);
    $this->assertSame($expected, $result);
  }

  /**
   * Tests processInbound never produces raw slashes after prefix.
   *
   * Verifies that the suffix after /json/path/ never contains
   * unencoded slash characters (security against path traversal).
   */
  public function testNoRawSlashesAfterPrefix(): void {
    $processor = new AliasPathProcessor();
    $request = $this->createMock(Request::class);

    $malicious = [
      '/json/path/../../etc/passwd',
      '/json/path/../../../',
      '/json/path/a/b/c/d/e',
    ];

    foreach ($malicious as $input) {
      $result = $processor->processInbound($input, $request);
      $prefix = '/json/path/';
      $suffix = substr($result, strlen($prefix));
      $this->assertStringNotContainsString('/', $suffix, sprintf(
        'Suffix "%s" should not contain raw slashes for input "%s"',
        $suffix,
        $input
      ));
    }
  }

  /**
   * Tests that paths with already-encoded segments are not double-encoded.
   */
  public function testNoDoubleEncoding(): void {
    $processor = new AliasPathProcessor();
    $request = $this->createMock(Request::class);

    $result = $processor->processInbound('/json/path/already%2Fencoded%2Fpath', $request);
    $this->assertSame('/json/path/already%2Fencoded%2Fpath', $result);
  }

  /**
   * Tests processInbound encodes all slashes in the suffix.
   *
   * The path processor is agnostic about what the suffix represents;
   * it simply encodes every '/' to '%2F', including segments that
   * look like language codes.
   */
  public function testAliasWithTwoLetterSuffix(): void {
    $processor = new AliasPathProcessor();
    $request = $this->createMock(Request::class);

    // 'about-us/en' has one slash between segments.
    $result = $processor->processInbound('/json/path/about-us/en', $request);
    $this->assertSame('/json/path/about-us%2Fen', $result);

    // 'deep/alias/fr' has two slashes.
    $result2 = $processor->processInbound('/json/path/deep/alias/fr', $request);
    $this->assertSame('/json/path/deep%2Falias%2Ffr', $result2);
  }

}
