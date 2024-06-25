<?php

namespace Drupal\entity_json_speed\Commands;

use Drush\Commands\DrushCommands;
use Drupal\entity_json_speed\Entities\GlobalEntities;

/**
 * Defines Drush commands for generating JSON files for entities.
 *
 * @package Drupal\entity_json_speed\Commands
 */
class EntityJSONGeneratorCommands extends DrushCommands {

  /**
   * The global entities service.
   *
   * @var \Drupal\entity_json_speed\Entities\GlobalEntities
   */
  protected $global_entities;

  protected $file_system;

  /**
   * Constructs a new EntityJSONGeneratorCommands object.
   *
   * @param \Drupal\entity_json_speed\Entities\GlobalEntities $global_entities
   *   The global entities service.
   */
  public function __construct(
    $global_entities,
    $file_system
) {
    $this->global_entities = $global_entities;
    $this->file_system = $file_system;

  }

  /**
   * Generates JSON files for entities.
   *
   * @command entity-json-speed:generate
   * @aliases ejg
   * @description Generates JSON files for specified entities.
   */
  public function generateFiles() {
    $all_entity_type = $this->global_entities->entity_types;
    foreach ($all_entity_type as $entity_type) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entity_ids = $storage->getQuery()->execute();
      foreach ($entity_ids as $entity_id) {
        $entity = $storage->load($entity_id);
        if(method_exists($entity, "getTranslationLanguages")) {
          $languages = $entity->getTranslationLanguages();
          foreach ($languages as $id => $language) {
            $translation = $entity->getTranslation($id);
            $this->global_entities->export($translation, FALSE);
          }  
        } else {
          $this->global_entities->export($entity, FALSE);
        }
      }
    }
  }

  /**
   * Deletes JSON files for entities.
   *
   * @command entity-json-speed:delete
   * @aliases ejd
   * @description Deletes JSON files for specified entities.
   */
  public function deleteFiles() {
    $this->file_system->deleteAllFiles();
  }

}
