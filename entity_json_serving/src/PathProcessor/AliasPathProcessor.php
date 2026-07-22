<?php

declare(strict_types=1);

namespace Drupal\entity_json_serving\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Encodes multi-segment aliases into a single route parameter.
 *
 * Drupal's route matching uses the number of URL segments (num_parts) to
 * filter candidate routes. A route with path '/json/path/{alias}' expects
 * exactly 3 segments, but multi-segment aliases like 'hola-mundo/3' produce
 * 4+ segments. This processor URL-encodes the slashes so the alias becomes a
 * single segment that the route can match.
 */
class AliasPathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $prefix = '/json/path/';

    if (!str_starts_with($path, $prefix)) {
      return $path;
    }

    $suffix = substr($path, strlen($prefix));

    if ($suffix === '') {
      return $path;
    }

    $encoded = str_replace('/', '%2F', $suffix);

    return $prefix . $encoded;
  }

}
