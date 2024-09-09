<?php
namespace Drupal\entity_json_speed\Test;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class BaseTestClass  {   

    protected $file_service;
    protected $entity_type;

    public function __construct($file_service, $entity_type) {
        $this->file_service = $file_service;
        $this->entity_type = $entity_type;
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
        // Crear traducción de la entidad
    }
}

