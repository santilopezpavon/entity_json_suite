<?php

namespace Drupal\entity_json_speed\Test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;
use Drupal\entity_json_speed\Test\BaseTestClass;

/**
 * Service to handle JSON export and deletion for entities.
 */
class TestAliasService extends BaseTestClass{

    private $name = 'ra9oui35j6b51bc0ochk4n48gj';
    private $content_type = 'article';
    private $lang_trans = 'es';

    public function __construct($file_service, $entity_type, $alias_service) {
        parent::__construct($file_service, $entity_type, $alias_service);

    }

    public function test() {

        $test = [
            [
                "name" => 'createAlias',
                "description" => "Crear entidad original y verificar que los ficheros se han creado"
            ],   
            [
                "name" => 'deleteAlias',
                "description" => "Crear entidad original y verificar que los ficheros se han creado"
            ],   
            [
                "name" => 'deleteAliasTrans',
                "description" => "Crear entidad original y verificar que los ficheros se han creado"
            ],        
        ];     

        $this->doTests($test);
    }

 

    public function createAlias() {
        $result = $this->createEntityAndTranslation("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ], $this->lang_trans);

        $paths = $this->alias_service->getPathAliasEntityByEntity($result["entity"]);
        $info_path_origin = $this->alias_service->getAliasInfo($paths)["alias_path_complete"];

        $paths = $this->alias_service->getPathAliasEntityByEntity($result["entity_trans"], $this->lang_trans);
        $info_path_trans = $this->alias_service->getAliasInfo($paths)["alias_path_complete"];

        $exits = file_exists($info_path_trans) && file_exists($info_path_origin);

        $result["entity_trans"]->delete();
        $result["entity"]->delete();           

        
        return $exits;                  
    }

    function deleteAlias() {
       
        $result = $this->createEntityAndTranslation("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ], $this->lang_trans);

        $paths = $this->alias_service->getPathAliasEntityByEntity($result["entity"]);
        $info_path_origin = $this->alias_service->getAliasInfo($paths)["alias_path_complete"];

        $paths = $this->alias_service->getPathAliasEntityByEntity($result["entity_trans"], $this->lang_trans);
        $info_path_trans = $this->alias_service->getAliasInfo($paths)["alias_path_complete"];

        //$result["entity_trans"]->delete();
        $result["entity"]->delete();           

        $success = !file_exists($info_path_trans) && !file_exists($info_path_origin);

        
        return $success;            

    }

    public function deleteAliasTrans() {
        $result = $this->createEntityAndTranslation("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ], $this->lang_trans);

        $paths = $this->alias_service->getPathAliasEntityByEntity($result["entity"]);
        $info_path_origin = $this->alias_service->getAliasInfo($paths)["alias_path_complete"];

        $paths_trans = $this->alias_service->getPathAliasEntityByEntity($result["entity_trans"], $this->lang_trans);
        $info_path_trans = $this->alias_service->getAliasInfo($paths_trans)["alias_path_complete"];
        dump($this->alias_service->getAliasInfo($paths_trans)["lang"]);
        $result["entity_trans"]->delete();

        $success = !file_exists($info_path_trans) && file_exists($info_path_origin);
        $result["entity"]->delete();           

        
        return $success;   
    }
  

}   