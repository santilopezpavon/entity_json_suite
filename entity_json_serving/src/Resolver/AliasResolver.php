<?php

declare(strict_types=1);

namespace Drupal\entity_json_serving\Resolver;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Resolves a path alias to entity type and ID using Drupal's alias manager.
 */
class AliasResolver {

  /**
   * The path alias manager.
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AliasResolver.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    AliasManagerInterface $alias_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->aliasManager = $alias_manager;
    $this->logger = $logger_factory->get('entity_json_serving');
  }

  /**
   * Resolves a path alias to entity type and ID.
   *
   * @param string $alias
   *   The alias path (e.g., 'about-us' or '/about-us').
   *
   * @return array|null
   *   An associative array with 'entity_type' and 'entity_id' keys,
   *   or NULL if the alias cannot be resolved.
   */
  public function resolve(string $alias): ?array {
    $alias = '/' . ltrim($alias, '/');

    $internalPath = $this->aliasManager->getPathByAlias($alias);

    if ($internalPath === $alias) {
      return NULL;
    }

    if (preg_match('#^/(\w+)/(\d+)$#', $internalPath, $matches)) {
      return [
        'entity_type' => $matches[1],
        'entity_id' => $matches[2],
      ];
    }

    $this->logger->warning('Unresolvable internal path "{path}" for alias "{alias}".', [
      'path' => $internalPath,
      'alias' => $alias,
    ]);

    return NULL;
  }

}
