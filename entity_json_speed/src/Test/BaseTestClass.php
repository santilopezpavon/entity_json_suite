<?php
namespace Drupal\entity_json_speed\Test;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class BaseTestClass  {   

    protected $file_service;
    protected $entity_type;
    protected $alias_service;

    public function __construct($file_service, $entity_type, $alias_service) {
        $this->file_service = $file_service;
        $this->entity_type = $entity_type;
        $this->alias_service = $alias_service;
    }

    public function doTests($test) {
        foreach ($test as $key => $value) {
            print_r("----------------------------------------" . "\n");
            print_r("Método: " . $value["name"] . "\n");
            print_r("Descripción:" . $value["description"] . "\n");

            $method_name = $value["name"];
            $test_passed = $this->$method_name();
            if ($test_passed == 1) {
                print_r("\033[32mResultado: " . $test_passed . "\033[0m\n");
            } else {
                print_r("\033[31mResultado: " . $test_passed . "\033[0m\n");
            }
        }
    }

    public function createEntity($content_type, $data) {
        $entity = $this->entity_type->getStorage($content_type)->create($data);      
        $entity->save(); 
        return $entity;    
    }

    public function createTranslation($entity, $lang) {
        $node_trans = $entity->addTranslation($lang, $entity->toArray());
        $node_trans->save();
        return $node_trans;
    }

    public function createEntityAndGetFiles($content_type, $data) {
        $entity = $this->createEntity($content_type, $data);
        $paths = $this->file_service->getPathsFilesEntity($entity);
        return [
            "entity" => $entity,
            "paths" => $paths
        ];
    }

    public function createTranslationAndGetFiles($entity, $lang) {
        $entity_trans = $this->createTranslation($entity, $lang);
        $paths = $this->file_service->getPathsFilesEntity($entity_trans);
        
        return [
            "entity_trans" => $entity,
            "paths_trans" => $paths
        ];
    }

    public function createEntityAndTranslation($content_type, $data, $lang_trans) {
        // Crear la entidad y obtener sus archivos
        $entityData = $this->createEntityAndGetFiles($content_type, $data);

        // Crear la traducción y obtener sus archivos
        $translationData = $this->createTranslationAndGetFiles($entityData['entity'], $lang_trans);

        return [
            "entity" => $entityData['entity'],
            "paths" => $entityData['paths'],
            "entity_trans" => $translationData['entity_trans'],
            "paths_trans" => $translationData['paths_trans']
        ];
    }
}

