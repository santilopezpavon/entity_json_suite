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
        
        dump("createBaseFiles");
        dump("----------------------------------------");

        $test_passed = $this->createBaseFiles();
        dump("createTranslationFields");
        dump("----------------------------------------");

        $test_passed = $this->createTranslationFields();
        dump("removeFilesOrigin");
        dump("----------------------------------------");

        $test_passed = $this->removeFilesOrigin();
        dump("removeFilesOriginTranslation");
        dump("----------------------------------------");

        $test_passed = $this->removeFilesOriginTranslation();

        dump("removeFilesOriginAndTranslation");
        dump("----------------------------------------");
        $test_passed = $this->removeFilesOriginAndTranslation();

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


        dump("Remove trans");
        dump($first_trans == true && $second_trans == true);    
        dump("Remove origin");
        dump($first_origin == true && $second_origin == true);    


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
        dump(true === ($first && $second));    
        
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
        dump(true === ($first && $second));    
        
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
        dump(true === ($first && $second));
        $node_trans->delete();
        $node->delete();
    }

}   