<?php

namespace Drupal\entity_json_speed\Entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;

/**
 * Service to handle JSON export and deletion for entities.
 */
class Alias {

  protected $serializer;
  protected $entityTypeManager;
  protected $file_service;

  /**
   * Constructor.
   */
  public function __construct($serializer, $entityTypeManager, $file_service) {
    $this->serializer = $serializer;
    $this->entityTypeManager = $entityTypeManager;
    $this->file_service = $file_service;
  }

  public function getPathAliasEntityByEntity($entity, $lang = null) {
    $source = "/" . $entity->getEntityTypeId() . "/" . $entity->id();
    $path_alias = $this->entityTypeManager->getStorage('path_alias')->loadByProperties(['path' => $source]);
    if(!empty($path_alias))  {
      $path_alias = reset($path_alias);
      if($lang !== null && $path_alias->hasTranslation($lang)) {
        $path_alias = $path_alias->getTranslation($lang);
      }
      return $path_alias;
    }
    return FALSE;
  }

  public function export($entity) {
    if($entity->getEntityTypeId() != 'path_alias') {
        return NULL;
    }
    $alias_info = $this->getAliasInfo($entity);

    $alias_entity_prior = $entity->original;

    if(!empty($alias_entity_prior)) {
      $alias_info_prior = $this->getAliasInfo($alias_entity_prior);
      $path_alias_prior = $alias_info_prior["alias_path"];
      $this->file_service->deleteFile($path_alias_prior);  
    }

    
    $entity = $alias_info["entity"];
    $file_data = $alias_info["file_data"];
    $path_alias = $alias_info["alias_path"];
    $this->file_service->saveData($path_alias, json_encode($file_data));


    \Drupal::service("entity_json_speed.global_entities")->export($entity);
   
  }

  public function delete($entity) {
    if($entity->getEntityTypeId() != 'path_alias') {
        return NULL;
    }

    $alias_info = $this->getAliasInfo($entity);
    $path_alias = $alias_info["alias_path"];

/*
    dump($entity);
    $alias_info = $this->getAliasInfo($entity);
    $path_alias = $alias_info["alias_path"];
    dump($alias_info);
    dump($path_alias);
    $lang = $entity->language()->getId();
    dump($lang);
    exit();*/

    $this->file_service->deleteFile($path_alias);

  }

  public function path($entity) {
    return $this->file_service->getPathFileEntity($entity);
  }

  public function getAliasInfo($entity) {
    $alias_info = $this->infoEntityAlias($entity);
    $file_data = $this->fileDataInfoAlias($alias_info);
    $alias_path = "/alias/" . $alias_info["lang"] . $alias_info["alias"] . '.json';
  
    return [
      "alias" => $alias_info["alias"],
      "source_path" => $alias_info["source_path"],
      "lang" => $alias_info["lang"],
      "target_type" => $alias_info["target_type"],
      "target_id" => $alias_info["target_id"],
      "entity" => $alias_info["entity"],
      "file_data" => $file_data,
      "alias_path" => $alias_path,
      "alias_path_complete" => $this->file_service->getPath($alias_path)
    ];
  
  }

  private function fileDataInfoAlias($alias_info) {
    $bundle = NULL;
    if(!empty($alias_info["entity"])) {
        $bundle = $alias_info["entity"]->bundle();
    }

    return [
        "target_type"=> $alias_info["target_type"],
        "target_id"=> $alias_info["target_id"],
        "lang"=> $alias_info["lang"],
        "bundle" => $bundle
      ];
  }

  private function infoEntityAlias($entity) {
    $alias = $entity->getAlias();

    $source_path = $entity->getPath();
    $array_explode = explode("/", $source_path);
    $lang_origin = $entity->language()->getId();
    
    $storage = $this->entityTypeManager->getStorage($array_explode[1]);
    $entity = $storage->load($array_explode[2]);
    if(!empty($entity)) {
        if(method_exists($entity, "hasTranslation") && $entity->hasTranslation($lang_origin)) {
            $entity = $entity->getTranslation($lang_origin);
        }
    }
    
    return [
      "alias" => $alias,
      "source_path" => $source_path,
      "lang" => $lang_origin,
      "target_type" => $array_explode[1],
      "target_id" => $array_explode[2],
      "entity" => $entity
    ];
  }

}
