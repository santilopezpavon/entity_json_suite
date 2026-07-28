<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_json_menu\Unit\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\entity_json_menu\Controller\MenuExportController;
use Drupal\entity_json_menu\Services\MenuExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the MenuExportController.
 *
 * @group entity_json_menu
 */
class MenuExportControllerTest extends TestCase {

  /**
   * The temporary test file path.
   */
  private string $tempFile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tempFile = tempnam(sys_get_temp_dir(), 'ejmt_');
    file_put_contents($this->tempFile, '{"menu":"test"}');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->tempFile);
    parent::tearDown();
  }

  /**
   * Creates a controller with mocked dependencies.
   */
  private function createController(
    ?MenuExporter $menuExporter = NULL,
    ?FileSystemInterface $fileSystem = NULL,
  ): MenuExportController {
    return new MenuExportController(
      $fileSystem ?? $this->createMock(FileSystemInterface::class),
      $menuExporter ?? $this->createMock(MenuExporter::class),
    );
  }

  /**
   * Creates a controller configured to serve a menu file successfully.
   */
  private function createControllerForServe(): MenuExportController {
    $menuExporter = $this->createMock(MenuExporter::class);
    $menuExporter->method('getFileUri')
      ->with('main')
      ->willReturn('public://menus/main.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')
      ->willReturn($this->tempFile);

    return $this->createController(
      menuExporter: $menuExporter,
      fileSystem: $fileSystem,
    );
  }

  /**
   * Tests serveMenu returns BinaryFileResponse when the file exists.
   */
  public function testServeMenuReturnsBinaryFileResponse(): void {
    $controller = $this->createControllerForServe();
    $request = new Request();

    $response = $controller->serveMenu($request, 'main');

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
  }

  /**
   * Tests serveMenu includes ETag and Last-Modified headers.
   */
  public function testServeMenuHasConditionalHeaders(): void {
    $controller = $this->createControllerForServe();

    $response = $controller->serveMenu(new Request(), 'main');

    $this->assertTrue($response->headers->has('ETag'));
    $this->assertTrue($response->headers->has('Last-Modified'));
  }

  /**
   * Tests serveMenu returns 304 Not Modified when ETag matches.
   */
  public function testServeMenuReturns304WhenNotModified(): void {
    $controller = $this->createControllerForServe();

    $response = $controller->serveMenu(new Request(), 'main');
    $etag = $response->headers->get('ETag');

    $request = new Request();
    $request->headers->set('If-None-Match', $etag);

    $response = $controller->serveMenu($request, 'main');

    $this->assertSame(304, $response->getStatusCode());
  }

  /**
   * Tests serveMenu throws NotFoundHttpException when realpath returns FALSE.
   */
  public function testServeMenuThrowsNotFoundWhenRealpathFalse(): void {
    $menuExporter = $this->createMock(MenuExporter::class);
    $menuExporter->method('getFileUri')
      ->with('missing-menu')
      ->willReturn('public://menus/missing-menu.json');

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn(FALSE);

    $controller = $this->createController(
      menuExporter: $menuExporter,
      fileSystem: $fileSystem,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Menu JSON file not found.');

    $controller->serveMenu(new Request(), 'missing-menu');
  }

  /**
   * Tests serveMenu throws NotFoundHttpException when file does not exist.
   */
  public function testServeMenuThrowsNotFoundWhenFileMissing(): void {
    $menuExporter = $this->createMock(MenuExporter::class);
    $menuExporter->method('getFileUri')
      ->with('deleted-menu')
      ->willReturn('public://menus/deleted-menu.json');

    $missingPath = '/tmp/nonexistent-menu-file.json';
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturn($missingPath);

    $controller = $this->createController(
      menuExporter: $menuExporter,
      fileSystem: $fileSystem,
    );

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Menu JSON file not found.');

    $controller->serveMenu(new Request(), 'deleted-menu');
  }

}
