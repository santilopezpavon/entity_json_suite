<?php

namespace Drupal\entity_json_speed\Entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;

/**
 * Service to handle JSON export and deletion for entities.
 */
class GlobalEntities {

  protected $serializer;
  protected $entityTypeManager;
  protected $file_service;
  protected $alias_manager;
  protected $language_manager;
  public $entity_types;


  /**
   * Constructor.
   */
  public function __construct($serializer, $entityTypeManager, $file_service, $alias_manager, $language_manager) {
    $this->serializer = $serializer;
    $this->entityTypeManager = $entityTypeManager;
    $this->file_service = $file_service;
    $this->entity_types = \Drupal::config('entity_json_speed.settings')->get('content_types_array');
    $this->alias_manager = $alias_manager;
    $this->language_manager = $language_manager;
    if(!is_array($this->entity_types)) {
      $this->entity_types = [];
    }
  }

  public function export($entity, $replace_file = TRUE) {
    
    if(!in_array($entity->getEntityTypeId() , $this->entity_types)) {
      return NULL;
    }
    $path_entity = $this->path($entity);
    $json_data = $this->serializer->serialize($entity, 'json', []);
    $json_data_array = json_decode($json_data, true);

    $json_data_array = $this->removeUnwantedProperties($json_data_array);
    $json_data_array["translation_info"] = $this->getTranslationData($entity);

    $json_data = json_encode($json_data_array);
    $this->file_service->saveData($path_entity, $json_data, $replace_file);
  }

  public function delete($entity) {
    if(!in_array($entity->getEntityTypeId() , $this->entity_types)) {
      return NULL;
    }
    $path_entity = $this->path($entity);
    $this->file_service->deleteFile($path_entity);
  }

  public function path($entity) {
    $langcode = 'neutral';

    try {
        $langcode = $entity->language()->getId();
    } catch (\Throwable $th) {
    }    
  
    $directory = "/" . $langcode . '/' . $entity->getEntityTypeId() . '/' . $entity->bundle();
    return  $directory . '/' . $entity->id() . '.json';  
  }

  protected function getTranslationData($entity) {
    $languages = $this->language_manager->getLanguages();
    $entity_languages = [];
    $entity_id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $entity_langs = [];

    foreach ($languages as $language) {
      $langcode = $language->getId();
      if ($entity->hasTranslation($langcode)) {
        array_push($entity_langs, $langcode);
        $translated_entity = $entity->getTranslation($langcode);
        $path = "/{$entity_type}/{$entity_id}";
        $alias = $this->alias_manager->getAliasByPath($path, $langcode);
        $entity_languages[$langcode] = $alias;
      }
    }

    return [
      "languages" => $entity_langs,
      "alias" => $entity_languages
    ];
  }

  protected function removeUnwantedProperties($data) {
    // Properties to remove.
    $unwanted_properties = [
        'vid', 'uuid', 'revision_id', 
        'revision_timestamp', 'revision_uid', 
        'uid','promote', 'sticky','revision_translation_affected', 
        'content_translation_source', 'content_translation_outdated'
    ];
  
    foreach ($unwanted_properties as  $key) {
      if(array_key_exists($key, $data)) {
        unset($data[$key]);
      }
    }
    return $data;
  }
}
