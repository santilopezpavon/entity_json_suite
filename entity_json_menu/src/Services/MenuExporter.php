<?php

declare(strict_types=1);

namespace Drupal\entity_json_menu\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Exports configured menus as static JSON files with recursive tree structure.
 */
class MenuExporter {

  /**
   * The menu link tree service.
   */
  protected MenuLinkTreeInterface $menuTree;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new MenuExporter.
   */
  public function __construct(
    MenuLinkTreeInterface $menu_tree,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->menuTree = $menu_tree;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('entity_json_menu');
  }

  /**
   * Exports all configured menus as JSON files.
   *
   * @return array[]
   *   An array of result arrays, each with keys: menu_id, success,
   *   and optional error.
   */
  public function exportAll(): array {
    $config = $this->configFactory->get('entity_json_menu.settings');
    $menus = $config->get('menus') ?? [];
    $results = [];

    foreach ($menus as $menu_id) {
      try {
        $this->exportMenu((string) $menu_id);
        $results[] = [
          'menu_id' => $menu_id,
          'success' => TRUE,
        ];
      }
      catch (\Throwable $e) {
        $this->logger->error('Failed to export menu {menu}: @msg', [
          'menu' => $menu_id,
          '@msg' => $e->getMessage(),
        ]);
        $results[] = [
          'menu_id' => $menu_id,
          'success' => FALSE,
          'error' => $e->getMessage(),
        ];
      }
    }

    return $results;
  }

  /**
   * Exports a single menu as a JSON file.
   *
   * @param string $menu_id
   *   The menu machine name.
   *
   * @return string
   *   The file URI where the JSON was saved.
   *
   * @throws \RuntimeException
   *   If the menu is not found.
   */
  public function exportMenu(string $menu_id): string {
    $menu_storage = $this->entityTypeManager->getStorage('menu');
    $menu_entity = $menu_storage->load($menu_id);

    if (!$menu_entity) {
      throw new \RuntimeException(sprintf('Menu "%s" not found.', $menu_id));
    }

    $parameters = new MenuTreeParameters();
    $tree = $this->menuTree->load($menu_id, $parameters);

    $tree_data = $this->buildTreeRecursive($tree);

    $data = [
      'menu' => [
        'id' => $menu_entity->id(),
        'label' => $menu_entity->label(),
        'description' => $menu_entity->getDescription(),
      ],
      'tree' => $tree_data,
    ];

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === FALSE) {
      throw new \RuntimeException(sprintf('Failed to encode menu "%s" as JSON.', $menu_id));
    }

    $uri = 'public://menus/' . $menu_id . '.json';
    $directory = dirname($uri);
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $this->fileSystem->saveData($json, $uri, FileSystemInterface::EXISTS_REPLACE);

    return $uri;
  }

  /**
   * Recursively builds the tree data from MenuLinkTreeElement objects.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $elements
   *   The tree elements to process.
   *
   * @return array[]
   *   An array of tree node arrays with recursive children.
   */
  protected function buildTreeRecursive(array $elements): array {
    $nodes = [];

    foreach ($elements as $element) {
      $link = $element->link;
      $url = $link->getUrlObject();

      $node = [
        'id' => $link->getPluginId(),
        'title' => (string) $link->getTitle(),
        'url' => $url->isRouted() ? $url->getInternalPath() : $url->toString(),
        'route_name' => $url->isRouted() ? $url->getRouteName() : '',
        'route_parameters' => $url->isRouted() ? $url->getRouteParameters() : [],
        'description' => (string) $link->getDescription(),
        'weight' => $link->getWeight(),
        'enabled' => $link->isEnabled(),
        'expanded' => $link->isExpanded(),
        'provider' => $link->getProvider(),
        'children' => [],
      ];

      if ($element->subtree !== []) {
        $node['children'] = $this->buildTreeRecursive($element->subtree);
      }

      $nodes[] = $node;
    }

    return $nodes;
  }

  /**
   * Returns the file URI for a given menu.
   */
  public function getFileUri(string $menu_id): string {
    return 'public://menus/' . $menu_id . '.json';
  }

}
