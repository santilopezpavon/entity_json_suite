<?php

namespace Drupal\entity_json_speed\Test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;
use Drupal\entity_json_speed\Test\BaseTestClass;

/**
 * Service to handle JSON export and deletion for entities.
 */
class TestService extends BaseTestClass{

    private $name = 'ra9oui35j6b51bc0ochk4n48gj';
    private $content_type = 'article';
    private $lang_trans = 'es';
    /*private $file_service;
    private $entity_type;*/

    public function __construct($file_service, $entity_type, $alias_service) {
        /*$this->file_service = $file_service;
        $this->entity_type = $entity_type;*/
        parent::__construct($file_service, $entity_type, $alias_service);

    }

    public function test() {

        $test = [
            [
                "name" => 'createBaseFiles',
                "description" => "Crear entidad original y verificar que los ficheros se han creado"
            ],
            [
                "name" => 'createTranslationFields',
                "description" => "Crear entidad original, crear traducción y verificar que los ficheros de la traducción se han creado"
            ],
            [
                "name" => 'removeFilesOrigin',
                "description" => "Crear entidad original, borrar y verificar que los ficheros se hayan eliminado"
            ],
            [
                "name" => "removeFilesOriginAndTranslation",
                "description" => "Crear entidad original, crear traducción, borrar original y verificar que ficheros de original y traducción se hayan borrado"
            ],
         
        
        ];

        $this->doTests($test);
    }

  

    public function createBaseFiles() {

        $node = $this->createEntity("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);            
      
        $paths = $this->file_service->getPathsFilesEntity($node);      
        $first = file_exists($paths['complete_path_file_config']);
        $second = file_exists($paths['complete_path_file_current']);

        $node->delete();

        return true === ($first && $second);             
    }
    
    public function removeFilesOriginAndTranslation() {
        $node = $this->createEntity("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);  
        
        $node_trans = $this->createTranslation($node, $this->lang_trans);

        $paths_trans = $this->file_service->getPathsFilesEntity($node_trans);
        $paths_origin = $this->file_service->getPathsFilesEntity($node);
        
        $node->delete();   

        $first_origin = !file_exists($paths_origin['complete_path_file_config']);
        $second_origin = !file_exists($paths_origin['complete_path_file_current']);

        $first_trans = !file_exists($paths_trans['complete_path_file_config']);
        $second_trans = !file_exists($paths_trans['complete_path_file_current']);


        return ($first_trans == true && $second_trans == true) && ($first_origin == true && $second_origin == true);
  


    }


    public function removeFilesOrigin() {
        $node = $this->createEntity("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);  
             
      
        $paths = $this->file_service->getPathsFilesEntity($node);  
        $node->delete();
    
        $first = !file_exists($paths['complete_path_file_config']);
        $second = !file_exists($paths['complete_path_file_current']);
        return true === ($first && $second);    
        
    }

    public function removeFilesOriginTranslation() {
        $node = $this->createEntity("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);  
        
        
        $node_trans = $this->createTranslation($node, $this->lang_trans);


        $paths = $this->file_service->getPathsFilesEntity($node_trans);    
        $node_trans->delete();
  
        $first = !file_exists($paths['complete_path_file_config']);
        $second = !file_exists($paths['complete_path_file_current']);
        dump($first != true && $second == true);    
        
        $node->delete();
    }


    

    public function createTranslationFields() {
        $node = $this->createEntity("node", [
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);              

        $node_trans = $this->createTranslation($node, $this->lang_trans);


        $paths = $this->file_service->getPathsFilesEntity($node_trans);

      
        $first = file_exists($paths['complete_path_file_config']);
        $second = file_exists($paths['complete_path_file_current']);
        $node_trans->delete();
        $node->delete();
        return true === ($first && $second);

    }

}   