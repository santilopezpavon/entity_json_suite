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
   * Constructs a new EntitySerializer object.
   *
   * @param \Drupal\headless_entity_serializer\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Symfony serializer service.
   */
  public function __construct($file_storage_manager, $serializer) {
    $this->fileStorageManager = $file_storage_manager;
    $this->serializer = $serializer;
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
    // Check if the entity has the getTranslationLanguages method, typically
    // found on ContentEntityInterface, indicating it might have multiple translations.
    if (method_exists($entity, "getTranslationLanguages")) {
      // Get all available translation languages for this entity.
      $languages = $entity->getTranslationLanguages();
      foreach ($languages as $id => $language) {
        // Get the specific translation of the entity.
        $translation = $entity->getTranslation($id);
        // Serialize the translated entity data to JSON.
        $this->processParagprahs($entity);

        $json_data = $this->serializer->serialize($translation, 'json', []);
        // Save the JSON data to a file using the FileStorageManager.
        $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $id);
      }
    }
    else {
      // For entities that are not translatable or do not implement
      // getTranslationLanguages, serialize the entity directly.
      $this->processParagprahs($entity);
      $json_data = $this->serializer->serialize($entity, 'json', []);
      // Save the JSON data to a file using the FileStorageManager.
      $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $languageId);
    }
  }

  /**
   *
   */
  protected function processParagprahs($entity) {
    dump("hola");
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_name => $field_definition) {
      if (
        $field_definition->getType() === 'entity_reference_revisions' &&
        $field_definition->getSetting('target_type') === 'paragraph'
      ) {
        if (!$entity->get($field_name)->isEmpty()) {
          foreach ($entity->get($field_name) as $item) {
            /** @var \Drupal\paragraphs\ParagraphInterface|null $paragraph */
            // This gets the actual entity object.
            $paragraph = $item->entity;
            dump("hola 2");
              // Recursively call exportEntity for the paragraph.
              // Note: A paragraph itself might have paragraph fields, so this
              // recursion will naturally handle nested paragraphs.
              $this->exportEntity($paragraph);

          }
        }

      }

    }

  }

}
