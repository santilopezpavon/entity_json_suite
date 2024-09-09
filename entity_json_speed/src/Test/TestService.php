<?php

namespace Drupal\entity_json_speed\Test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;

/**
 * Service to handle JSON export and deletion for entities.
 */
class TestService {

    private $name = 'ra9oui35j6b51bc0ochk4n48gj';
    private $content_type = 'article';
    private $lang_trans = 'es';

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
            ]
        ];

        foreach ($test as $key => $value) {
            print_r("----------------------------------------" . "\n");
            print_r("Método: " . $value["name"] . "\n");
            print_r("Descripción:" . $value["description"] . "\n");

            $method_name = $value["name"];
            $test_passed = $this->$method_name();
            if ($test_passed == 1) {
                // Código de escape ANSI para verde
                print_r("\033[32mResultado: " . $test_passed . "\033[0m\n");
            } else {
                // Código de escape ANSI para rojo
                print_r("\033[31mResultado: " . $test_passed . "\033[0m\n");
            }
        

        }
     
    }

    public function createBaseFiles() {

        $ejsf = \Drupal::service('entity_json_speed.file');
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->create([
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);      
        $node->save();       
      
        $paths = $ejsf->getPathsFilesEntity($node);      
        $first = file_exists($paths['complete_path_file_config']);
        $second = file_exists($paths['complete_path_file_current']);

        $node->delete();

        return true === ($first && $second);             
    }
    
    public function removeFilesOriginAndTranslation() {
        $ejsf = \Drupal::service('entity_json_speed.file');
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->create([
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);      
        $node->save();   
        
        $node_trans = $node->addTranslation($this->lang_trans, $node->toArray());
        $node_trans->save();

        $paths_trans = $ejsf->getPathsFilesEntity($node_trans);
        $paths_origin = $ejsf->getPathsFilesEntity($node);
        
        $node->delete();   

        $first_origin = !file_exists($paths_origin['complete_path_file_config']);
        $second_origin = !file_exists($paths_origin['complete_path_file_current']);

        $first_trans = !file_exists($paths_trans['complete_path_file_config']);
        $second_trans = !file_exists($paths_trans['complete_path_file_current']);


        return ($first_trans == true && $second_trans == true) && ($first_origin == true && $second_origin == true);
  


    }


    public function removeFilesOrigin() {
        $ejsf = \Drupal::service('entity_json_speed.file');
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->create([
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);      
        $node->save();       
      
        $paths = $ejsf->getPathsFilesEntity($node);  
        $node->delete();
    
        $first = !file_exists($paths['complete_path_file_config']);
        $second = !file_exists($paths['complete_path_file_current']);
        return true === ($first && $second);    
        
    }

    public function removeFilesOriginTranslation() {
        $ejsf = \Drupal::service('entity_json_speed.file');
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->create([
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);      
        $node->save();   
        
        $node_trans = $node->addTranslation($this->lang_trans, $node->toArray());
        $node_trans->save();

        $paths = $ejsf->getPathsFilesEntity($node_trans);    
        $node_trans->delete();
  
        $first = !file_exists($paths['complete_path_file_config']);
        $second = !file_exists($paths['complete_path_file_current']);
        dump($first != true && $second == true);    
        
        $node->delete();
    }


    

    public function createTranslationFields() {
        $node = \Drupal::service('entity_type.manager')->getStorage('node')->create([
            'type' => $this->content_type,
            'title' => $this->name,
            'status' => 1,
        ]);
        $node->save();       

        $node_trans = $node->addTranslation($this->lang_trans, $node->toArray());
        $node_trans->save();

        $ejsf = \Drupal::service('entity_json_speed.file');
        $paths = $ejsf->getPathsFilesEntity($node_trans);

      
        $first = file_exists($paths['complete_path_file_config']);
        $second = file_exists($paths['complete_path_file_current']);
        $node_trans->delete();
        $node->delete();
        return true === ($first && $second);

    }

}   