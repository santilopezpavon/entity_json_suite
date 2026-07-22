<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Services\Serialize;

use Drupal\Core\Entity\ContentEntityInterface;

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
   * @var \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager
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
   * @param \Drupal\headless_entity_serializer\Services\Storage\FileStorageManager $file_storage_manager
   *   The file storage manager service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The Symfony serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to export.
   * @param array &$processed
   *   Set of already processed entity keys to prevent infinite recursion.
   *   Passed by reference across recursive calls.
   */
  public function exportEntity(ContentEntityInterface $entity, array &$processed = []) {
    $key = $entity->getEntityTypeId() . '/' . $entity->id();
    if (in_array($key, $processed)) {
      return;
    }
    $processed[] = $key;

    $entityTypeId = $entity->getEntityTypeId();
    $languageId = $entity->language()->getId();
    $entityId = $entity->id();

    $this->processInlineEntities($entity, $processed);

    if ($entity->isTranslatable()) {
      $languages = $entity->getTranslationLanguages();
      foreach ($languages as $id => $language) {
        $translation = $entity->getTranslation($id);
        $json_data = $this->serializer->serialize($translation, 'json', []);
        $this->fileStorageManager->saveData($json_data, $entityId, $entityTypeId, $id);
      }
    }
    else {
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
   * entities are also exported if desired. Recursion is prevented via the
   * $processed set.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose fields are to be processed for inline entity exports.
   * @param array &$processed
   *   Set of already processed entity keys to prevent infinite recursion.
   */
  protected function processInlineEntities(ContentEntityInterface $entity, array &$processed) {
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_name => $field_definition) {
      $targetType = $field_definition->getSetting('target_type');
      if (array_key_exists($targetType, $this->entitiesInline) && !$entity->get($field_name)->isEmpty()) {
        foreach ($entity->get($field_name) as $item) {
          $referenced_entity = $item->entity ?? NULL;
          if ($referenced_entity instanceof ContentEntityInterface) {
            $this->exportEntity($referenced_entity, $processed);
          }
        }
      }
    }

  }

}
