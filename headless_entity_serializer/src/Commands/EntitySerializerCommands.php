<?php

namespace Drupal\headless_entity_serializer\Commands;

use Drupal\headless_entity_serializer\Services\Generator\GeneratorService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for serializing Drupal entities to JSON files.
 */
class EntitySerializerCommands extends DrushCommands {

  /**
   * The generator service for serializing entities.
   *
   * @var \Drupal\headless_entity_serializer\Service\Generator\GeneratorService
   */
  protected $generator;

  /**
   * Constructs a new EntitySerializerCommands object.
   *
   * This is the recommended way to inject services into Drush commands.
   *
   * @param \Drupal\headless_entity_serializer\Service\Generator\GeneratorService $generator
   *   The generator service.
   */
  public function __construct(GeneratorService $generator) {
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
  public function fullRegenerate($entity_type_id = NULL) {
    $this->io()->section('Executing full regeneration of serialized entities and aliases');
    if ($entity_type_id === NULL) {
      $this->generator->fullGenerate();
    }
    else {
      $this->generator->fullGenerateEntityType($entity_type_id);
    }
  }

  /**
   *
   * @command headless-entity-serializer:reset-state
   * @aliases hes-reset-state
   * @usage hes-reset-state
   * Fully regenerates JSON files for all configured entity types.
   */
  public function resetState($entity_type_id = NULL) {
    $this->io()->section('Init to reset the state');
    $this->generator->resetState();    
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
    $this->io()->section('Executing incremental update of serialized entities and aliases');
    $this->generator->incrementalGenerate();
  }



  /**
   *
   * @command headless-entity-serializer:test
   * @aliases hes-test
   * @usage hes-test
   */
  public function test() {
    $this->io()->section('Init to reset the state');
    /*$nids = \Drupal::entityQuery('node')->execute(); 

    foreach ($nids as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      if ($node) {
        $node->delete();
      }
    }*/
    //$result = \Drupal::service("headless_entity_serializer.file_storage_manager")->getEntitiesInFiles("node");
    //dump($result);
  }

}
