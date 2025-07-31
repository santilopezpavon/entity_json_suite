<?php

namespace Drupal\headless_entity_serializer\Services\Serialize;

/**
 * Service for serializing Drupal entities into JSON files.
 *
 * This service handles the serialization of entities, including their
 * translations, and delegates the saving of the resulting JSON data
 * to the FileStorageManager.
 */
class EntitySerializer {

  /**
   * The file storage manager service.
   *
   * @var \Drupal\headless_entity_serializer\Storage\FileStorageManager
   */
  protected $fileStorageManager;

  /**
   * The Symfony serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Configuration array for entity types that should be serialized inline.
   *
   * This property holds an array of entity types configured to be serialized
   * as part of their referencing entity, rather than as separate top-level
   * JSON files.
   *
   * @var array
   */
  protected $entitiesInline;

  /**
   * Constructs a new EntitySerializer object.
   *
   * @param \Drupal\headless_entity_serializer\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Symfony serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.   *.
   */
  public function __construct($file_storage_manager, $serializer, $config_factory) {
    $this->fileStorageManager = $file_storage_manager;
    $this->serializer = $serializer;
    $config = $config_factory->get('headless_entity_serializer.settings');
    $this->entitiesInline = $config->get('entity_types_inline');
  }

  /**
   * Exports an entity (and all its translations if applicable) to JSON files.
   *
   * For translatable entities, this method iterates through all available
   * translations and serializes each one into a separate JSON file. For
   * non-translatable entities or those without explicit translations,
   * it serializes the default entity. The generated JSON files are saved
   * using the FileStorageManager.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export. This can be any entity type, but its translation
   *   behavior will depend on its implementation.
   */
  public function exportEntity($entity) {
    $entityTypeId = $entity->getEntityTypeId();
    $languageId = $entity->language()->getId();
    $entityId = $entity->id();
    if (method_exists($entity, "getTranslationLanguages")) {
      // Get all available translation languages for this entity.
      $languages = $entity->getTranslationLanguages();
      foreach ($languages as $id => $language) {
        $translation = $entity->getTranslation($id);
        $this->processInlineEntities($entity);
        $json_data = $this->serializer->serialize($translation, 'json', []);
        $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $id);
      }
    }
    else {
      $this->processInlineEntities($entity);
      $json_data = $this->serializer->serialize($entity, 'json', []);
      $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $languageId);
    }
  }

  /**
   * Processes entity reference inline and recursively exports.
   *
   * This method iterates through the field definitions of the given entity.
   * If a field is an entity reference and its target entity type is configured
   * to be serialized "inline" (meaning it should be exported along with the
   * main entity), then the referenced entities are recursively passed to the
   * `exportEntity` method for serialization. This ensures that related
   * entities are also exported if desired.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose fields are to be processed for inline entity exports.
   */
  protected function processInlineEntities($entity) {
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_name => $field_definition) {
      $targetType = $field_definition->getSetting('target_type');
      if (array_key_exists($targetType, $this->entitiesInline)) {
        if (!$entity->get($field_name)->isEmpty()) {
          foreach ($entity->get($field_name) as $item) {
            $entityInline = $item->entity;
            $this->exportEntity($entityInline);
          }
        }
      }
    }

  }

}
