<?php

namespace Drupal\headless_entity_serializer\Services\Serialize;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\SerializerInterface;


class EntitySerializer {
    
    protected $fileStorageManager;

    protected $serializer;

    public function __construct($file_storage_manager, $serializer) {
        $this->fileStorageManager = $file_storage_manager;
        $this->serializer = $serializer;
    }

    public function exportEntity($entity) {
        $entityTypeId = $entity->getEntityTypeId();
        $languageId = $entity->language()->getId();
        $entityId = $entity->id();
        
        if (method_exists($entity, "getTranslationLanguages")) {
            $languages = $entity->getTranslationLanguages();
            foreach ($languages as $id => $language) {
                $translation = $entity->getTranslation($id);
                $json_data = $this->serializer->serialize($translation, 'json', []);
                $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $id);
        
            }
        } else {
            $json_data = $this->serializer->serialize($entity, 'json', []);
            $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $languageId);
    
        }
    }
}