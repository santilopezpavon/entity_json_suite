<?php

namespace Drupal\headless_entity_serializer\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\headless_entity_serializer\Service\EntitySerializerService;

/**
 * Drush commands for serializing Drupal entities to JSON files.
 */
class EntitySerializerCommands extends DrushCommands {

  protected $generator;
  /**
   * The entity serializer service.
   *
   * @var \Drupal\headless_entity_serializer\Service\EntitySerializerService
   */
  protected $entitySerializerService;

  public function __construct($generator) {
    $this->generator = $generator;
  }


  /**
   * Fully regenerates JSON files for selected entity types.
   *
   * This command deletes all existing serialized files for the configured
   * entity types and then re-generates them from scratch.
   *
   * @command headless-entity-serializer:full-regenerate
   * @aliases hes-full
   * @usage hes-full
   * Fully regenerates JSON files for all configured entity types.
   */
  public function fullRegenerate() {
    $this->io()->section('Ejecutando regeneración completa');
    $this->generator->fullGenerate();

    /*$result = $this->entitySerializerService->fullRegenerate();

    if ($result['status']) {
      $this->io()->success($result['message']);
    } else {
      $this->io()->error($result['message']);
    }*/
  }

  /**
   * Performs an incremental update of serialized entity JSON files.
   *
   * This command identifies new, updated, or deleted entities since the last
   * incremental run and processes them.
   *
   * @command headless-entity-serializer:incremental-update
   * @aliases hes-incremental
   * @usage hes-incremental
   * Performs an incremental update for configured entity types.
   */
  public function incrementalUpdate() {
    $this->io()->section('Ejecutando actualización incremental');
    $this->generator->incrementalGenerate();

/*
    if ($result['status']) {
      $this->io()->success($result['message']);
    } else {
      $this->io()->error($result['message']);
    } */
  }

}
