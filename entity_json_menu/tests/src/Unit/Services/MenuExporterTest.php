<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_json_menu\Unit\Services;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\entity_json_menu\Services\MenuExporter;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the MenuExporter service.
 *
 * @group entity_json_menu
 */
class MenuExporterTest extends TestCase {

  /**
   * Creates a mock menu link with the given properties.
   */
  private function createMockMenuLink(
    string $pluginId = 'menu_link_content:1',
    string $title = 'Home',
    ?Url $url = NULL,
    string $description = '',
    int $weight = 0,
    bool $enabled = TRUE,
    bool $expanded = FALSE,
    string $provider = 'menu_link_content',
  ): MenuLinkInterface {
    $link = $this->createMock(MenuLinkInterface::class);
    $link->method('getPluginId')->willReturn($pluginId);
    $link->method('getTitle')->willReturn($title);
    $link->method('getUrlObject')->willReturn($url ?? $this->createMockRoutedUrl());
    $link->method('getDescription')->willReturn($description);
    $link->method('getWeight')->willReturn($weight);
    $link->method('isEnabled')->willReturn($enabled);
    $link->method('isExpanded')->willReturn($expanded);
    $link->method('getProvider')->willReturn($provider);

    return $link;
  }

  /**
   * Creates a mock routed URL.
   */
  private function createMockRoutedUrl(
    string $internalPath = '/node/1',
    string $routeName = 'entity.node.canonical',
    array $routeParameters = ['node' => '1'],
  ): Url {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(TRUE);
    $url->method('getInternalPath')->willReturn($internalPath);
    $url->method('toString')->willReturn($internalPath);
    $url->method('getRouteName')->willReturn($routeName);
    $url->method('getRouteParameters')->willReturn($routeParameters);

    return $url;
  }

  /**
   * Creates a mock non-routed URL.
   */
  private function createMockNonRoutedUrl(string $uri = 'https://example.com'): Url {
    $url = $this->createMock(Url::class);
    $url->method('isRouted')->willReturn(FALSE);
    $url->method('toString')->willReturn($uri);

    return $url;
  }

  /**
   * Creates a MenuLinkTreeElement with the given link and subtree.
   */
  private function createTreeElement(
    MenuLinkInterface $link,
    array $subtree = [],
    bool $hasChildren = FALSE,
    int $depth = 0,
  ): MenuLinkTreeElement {
    return new MenuLinkTreeElement($link, $hasChildren, $depth, FALSE, $subtree);
  }

  /**
   * Creates a MenuExporter with full mock dependencies.
   */
  private function createExporter(
    ?MenuLinkTreeInterface $menuTree = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?ConfigFactoryInterface $configFactory = NULL,
    ?FileSystemInterface $fileSystem = NULL,
    ?LoggerChannelFactoryInterface $loggerFactory = NULL,
  ): MenuExporter {
    return new MenuExporter(
      $menuTree ?? $this->createMock(MenuLinkTreeInterface::class),
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
      $configFactory ?? $this->createMock(ConfigFactoryInterface::class),
      $fileSystem ?? $this->createMock(FileSystemInterface::class),
      $loggerFactory ?? $this->createMockLoggerFactory(),
    );
  }

  /**
   * Creates a mock logger factory returning a silent logger.
   */
  private function createMockLoggerFactory(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);

    return $factory;
  }

  /**
   * Creates a mock config factory that returns the given menus list.
   */
  private function createMockConfigFactory(array $menus): ConfigFactoryInterface {
    $config = $this->createMock(Config::class);
    $config->method('get')
      ->with('menus')
      ->willReturn($menus);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('entity_json_menu.settings')
      ->willReturn($config);

    return $factory;
  }

  /**
   * Creates a mock entity type manager with a menu storage.
   */
  private function createEntityTypeManagerWithMenu(?MenuInterface $menuEntity): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($menuEntity);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('menu')
      ->willReturn($storage);

    return $entityTypeManager;
  }

  /**
   * Creates a mock menu config entity.
   */
  private function createMockMenuEntity(string $id = 'main', string $label = 'Main navigation', string $description = ''): MenuInterface {
    $menu = $this->createMock(MenuInterface::class);
    $menu->method('id')->willReturn($id);
    $menu->method('label')->willReturn($label);
    $menu->method('getDescription')->willReturn($description);

    return $menu;
  }

  /**
   * Tests buildTreeRecursive with a single node.
   */
  public function testBuildTreeRecursiveSingleNode(): void {
    $link = $this->createMockMenuLink('menu_link_content:1', 'Home');
    $element = $this->createTreeElement($link);

    $exporter = $this->createExporter();
    $method = new \ReflectionMethod(MenuExporter::class, 'buildTreeRecursive');

    $nodes = $method->invoke($exporter, [$element]);

    $this->assertCount(1, $nodes);
    $this->assertSame('menu_link_content:1', $nodes[0]['id']);
    $this->assertSame('Home', $nodes[0]['title']);
    $this->assertSame('/node/1', $nodes[0]['url']);
    $this->assertSame('entity.node.canonical', $nodes[0]['route_name']);
    $this->assertSame(['node' => '1'], $nodes[0]['route_parameters']);
    $this->assertSame(0, $nodes[0]['weight']);
    $this->assertTrue($nodes[0]['enabled']);
    $this->assertFalse($nodes[0]['expanded']);
    $this->assertSame([], $nodes[0]['children']);
  }

  /**
   * Tests buildTreeRecursive with nested children.
   */
  public function testBuildTreeRecursiveNestedNodes(): void {
    $childLink = $this->createMockMenuLink('menu_link_content:3', 'Grandchild');

    $parentLink = $this->createMockMenuLink('menu_link_content:2', 'Child', weight: 10);
    $parentElement = $this->createTreeElement($parentLink, [
      $this->createTreeElement($childLink),
    ]);

    $rootLink = $this->createMockMenuLink('menu_link_content:1', 'Root');
    $rootElement = $this->createTreeElement($rootLink, [$parentElement]);

    $exporter = $this->createExporter();
    $method = new \ReflectionMethod(MenuExporter::class, 'buildTreeRecursive');

    $nodes = $method->invoke($exporter, [$rootElement]);

    $this->assertCount(1, $nodes);
    $this->assertSame('Root', $nodes[0]['title']);
    $this->assertCount(1, $nodes[0]['children']);
    $this->assertSame('Child', $nodes[0]['children'][0]['title']);
    $this->assertCount(1, $nodes[0]['children'][0]['children']);
    $this->assertSame('Grandchild', $nodes[0]['children'][0]['children'][0]['title']);
  }

  /**
   * Tests buildTreeRecursive with empty elements array.
   */
  public function testBuildTreeRecursiveEmptyTree(): void {
    $exporter = $this->createExporter();
    $method = new \ReflectionMethod(MenuExporter::class, 'buildTreeRecursive');

    $nodes = $method->invoke($exporter, []);

    $this->assertSame([], $nodes);
  }

  /**
   * Tests buildTreeRecursive with a non-routed (external) URL.
   */
  public function testBuildTreeRecursiveNonRoutedUrl(): void {
    $link = $this->createMockMenuLink(
      'menu_link_content:ext',
      'External',
      $this->createMockNonRoutedUrl('https://example.com'),
    );
    $element = $this->createTreeElement($link);

    $exporter = $this->createExporter();
    $method = new \ReflectionMethod(MenuExporter::class, 'buildTreeRecursive');

    $nodes = $method->invoke($exporter, [$element]);

    $this->assertSame('https://example.com', $nodes[0]['url']);
    $this->assertSame('', $nodes[0]['route_name']);
    $this->assertSame([], $nodes[0]['route_parameters']);
  }

  /**
   * Tests getFileUri returns the expected URI.
   */
  public function testGetFileUri(): void {
    $exporter = $this->createExporter();

    $this->assertSame('public://menus/main.json', $exporter->getFileUri('main'));
    $this->assertSame('public://menus/footer.json', $exporter->getFileUri('footer'));
  }

  /**
   * Tests exportMenu builds the JSON structure correctly.
   */
  public function testExportMenuBuildsCorrectJson(): void {
    $menuEntity = $this->createMockMenuEntity('main', 'Main navigation', 'Site main menu');
    $entityTypeManager = $this->createEntityTypeManagerWithMenu($menuEntity);

    $link = $this->createMockMenuLink('menu_link_content:1', 'Home');
    $treeElement = $this->createTreeElement($link);

    $menuTree = $this->createMock(MenuLinkTreeInterface::class);
    $menuTree->method('load')
      ->with('main', $this->isInstanceOf(MenuTreeParameters::class))
      ->willReturn([$treeElement]);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->with('public://menus', FileSystemInterface::CREATE_DIRECTORY);
    $fileSystem->expects($this->once())
      ->method('saveData')
      ->with(
        $this->callback(function (string $json): bool {
          $data = json_decode($json, TRUE);
          return is_array($data)
            && $data['menu']['id'] === 'main'
            && $data['menu']['label'] === 'Main navigation'
            && $data['menu']['description'] === 'Site main menu'
            && count($data['tree']) === 1
            && $data['tree'][0]['title'] === 'Home';
        }),
        'public://menus/main.json',
        FileSystemInterface::EXISTS_REPLACE,
      );

    $exporter = $this->createExporter(
      menuTree: $menuTree,
      entityTypeManager: $entityTypeManager,
      fileSystem: $fileSystem,
    );

    $uri = $exporter->exportMenu('main');

    $this->assertSame('public://menus/main.json', $uri);
  }

  /**
   * Tests exportMenu throws exception when menu is not found.
   */
  public function testExportMenuThrowsOnMissingMenu(): void {
    $entityTypeManager = $this->createEntityTypeManagerWithMenu(NULL);

    $exporter = $this->createExporter(entityTypeManager: $entityTypeManager);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Menu "nonexistent" not found.');

    $exporter->exportMenu('nonexistent');
  }

  /**
   * Tests exportAll exports configured menus and returns results.
   */
  public function testExportAllExportsConfiguredMenus(): void {
    $configFactory = $this->createMockConfigFactory(['main', 'footer']);

    $menuEntity = $this->createMockMenuEntity('main', 'Main navigation');
    $entityTypeManager = $this->createEntityTypeManagerWithMenu($menuEntity);

    $link = $this->createMockMenuLink('menu_link_content:1', 'Home');

    $menuTree = $this->createMock(MenuLinkTreeInterface::class);
    $menuTree->method('load')->willReturn([$this->createTreeElement($link)]);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('prepareDirectory');
    $fileSystem->method('saveData');

    $exporter = $this->createExporter(
      menuTree: $menuTree,
      entityTypeManager: $entityTypeManager,
      configFactory: $configFactory,
      fileSystem: $fileSystem,
    );

    $results = $exporter->exportAll();

    $this->assertCount(2, $results);
    $this->assertTrue($results[0]['success']);
    $this->assertSame('main', $results[0]['menu_id']);
    $this->assertTrue($results[1]['success']);
    $this->assertSame('footer', $results[1]['menu_id']);
  }

  /**
   * Tests exportAll catches errors and returns them as failed results.
   */
  public function testExportAllCatchesErrors(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Failed to export menu'),
        $this->callback(fn(array $ctx): bool => $ctx['menu'] === 'bad-menu'),
      );

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $configFactory = $this->createMockConfigFactory(['bad-menu']);

    $entityTypeManager = $this->createEntityTypeManagerWithMenu(NULL);

    $exporter = $this->createExporter(
      entityTypeManager: $entityTypeManager,
      configFactory: $configFactory,
      loggerFactory: $loggerFactory,
    );

    $results = $exporter->exportAll();

    $this->assertCount(1, $results);
    $this->assertFalse($results[0]['success']);
    $this->assertSame('bad-menu', $results[0]['menu_id']);
    $this->assertNotEmpty($results[0]['error']);
  }

  /**
   * Tests exportAll with no configured menus returns empty array.
   */
  public function testExportAllReturnsEmptyWhenNoMenusConfigured(): void {
    $configFactory = $this->createMockConfigFactory([]);

    $exporter = $this->createExporter(configFactory: $configFactory);

    $results = $exporter->exportAll();

    $this->assertSame([], $results);
  }

}
