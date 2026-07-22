<?php

declare(strict_types=1);

namespace Drupal\headless_entity_serializer\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a settings form for the Headless Entity Serializer module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new SettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    EntityFieldManagerInterface $entity_field_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->entityFieldManager = $entity_field_manager;
    $this->logger = $logger_factory->get('headless_entity_serializer');
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory'),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['headless_entity_serializer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'headless_entity_serializer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('headless_entity_serializer.settings');
    $content_types = $this->getAllContentTypesOptions();

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity Types to Serialize'),
      '#description' => $this->t('Select the entity types you want to serialize to JSON files.'),
      '#options' => $content_types["options"],
      '#default_value' => $config->get('entity_types') ?: [],
    ];

    $form['entity_types_inline'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity Types to Serialize in inline entity form'),
      '#description' => $this->t('Select the entity types you want to serialize to JSON files.'),
      '#options' => $content_types["optionsNotChange"],
      '#default_value' => $config->get('entity_types_inline') ?: [],
    ];

    $form['destination_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination Directory'),
      '#description' => $this->t("Absolute or relative path where the JSON files will be saved. It's recommended that they be stored outside the public directory."),
      '#default_value' => $config->get('destination_directory'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $directory = $form_state->getValue('destination_directory');

    // Validate directory existence and writability without side effects.
    if (!is_dir($directory)) {
      $parent = dirname((string) $directory);
      if (!is_dir($parent) || !is_writable($parent)) {
        $form_state->setErrorByName('destination_directory', $this->t(
          'The destination directory does not exist and its parent is not writable: @directory.',
          ['@directory' => $directory]
        ));
      }
    }
    elseif (!is_writable($directory)) {
      $form_state->setErrorByName('destination_directory', $this->t(
        'The destination directory is not writable: @directory. Please check permissions.',
        ['@directory' => $directory]
      ));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Ensure destination directory exists before saving.
    $directory = $form_state->getValue('destination_directory');
    if (!is_dir($directory)) {
      try {
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      }
      catch (\Throwable $e) {
        $this->logger->error('Failed to create destination directory: @message', [
          '@message' => $e->getMessage(),
        ]);
        $this->messenger->addError($this->t(
          'Could not create the destination directory: @directory.',
          ['@directory' => $directory]
        ));
        return;
      }
    }

    $this->config('headless_entity_serializer.settings')
      ->set('entity_types', array_filter($form_state->getValue('entity_types')))
      ->set('entity_types_inline', array_filter($form_state->getValue('entity_types_inline')))
      ->set('destination_directory', $directory)
      ->save();

    $this->messenger->addStatus($this->t('The entity serialization configuration has been saved.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Gets all available entity types for the form options.
   *
   * @return array
   *   An array of entity type labels keyed by entity type ID.
   */
  private function getAllContentTypesOptions() {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    $optionsNotChange = [];
    foreach ($definitions as $key => $value) {
      if ($value instanceof ContentEntityType) {
        $baseFields = $this->entityFieldManager->getBaseFieldDefinitions($key);
        $label = $value->getLabel();
        if (array_key_exists("changed", $baseFields)) {
          $options[$key] = $label;
        }
        else {
          $optionsNotChange[$key] = $label;
        }
      }
    }
    asort($options);
    return ["options" => $options, "optionsNotChange" => $optionsNotChange];
  }

}
