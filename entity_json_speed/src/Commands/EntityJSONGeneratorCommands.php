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
    
    // Get all entity types to JSON serialize.
    $all_entity_type = $this->global_entities->entity_types;
    
    if(empty($all_entity_type)) {
      throw new Exception("Error there aren't entity types for export configurates, please configure some one.", 1);      
    }

    foreach ($all_entity_type as $entity_type) {
      // Get the IDs of current entity types
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entity_ids = $storage->getQuery()->execute();

      foreach ($entity_ids as $entity_id) {
        // Load entity
        $entity = $storage->load($entity_id);
        if(method_exists($entity, "getTranslationLanguages")) {
          // If entity has in multiple languages.
          $languages = $entity->getTranslationLanguages();
          foreach ($languages as $id => $language) {
            $translation = $entity->getTranslation($id);
            $this->global_entities->export($translation, FALSE);
          }  
        } else {
          // If entity is not in multiple langs.
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


   /**
   * Test functionalities.
   *
   * @command entity-json-speed:test
   * @aliases ejt
   */

   public function test() {
    dump("Init Tests");
    \Drupal::service("entity_json_speed.test_service")->test();
    dump("End Tests");

   }
    
  

}
