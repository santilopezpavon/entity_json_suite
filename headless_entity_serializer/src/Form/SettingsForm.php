<?php

namespace Drupal\headless_entity_serializer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface; // Importa la interfaz
use Symfony\Component\DependencyInjection\ContainerInterface; // Importa para la inyección
use Drupal\Core\Messenger\MessengerInterface; // Importa la interfaz para Messenger

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
   * An array of entity types considered compatible for serialization.
   *
   * @var string[]
   */
  private $entity_types_compatibles = ["node", "path_alias", "media", "paragraph", "menu", "taxonomy_term"];

  /**
   * Constructs a new SettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    // No llames a parent::__construct() aquí. ConfigFormBase no lo requiere
    // con argumentos y su inicialización se maneja por su propia estructura.
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger; // Asigna el servicio de mensajería
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger') // Inyecta el servicio 'messenger'
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
    $content_types = $this->getAllContentTypes();

    $form['entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tipos de Entidad a Serializar'),
      '#description' => $this->t('Selecciona los tipos de entidad que deseas serializar a ficheros JSON.'),
      '#options' => $content_types,
      '#default_value' => $config->get('entity_types') ?: [],
    ];

    $form['destination_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directorio de Destino'),
      '#description' => $this->t('Ruta absoluta o relativa donde se guardarán los ficheros JSON. Se recomienda fuera del directorio público (ej. ../serialized_data).'),
      '#default_value' => $config->get('destination_directory'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Implementación de validación de directorio.
    $directory = $form_state->getValue('destination_directory');
    if (!is_dir($directory)) {
      if (!@mkdir($directory, 0775, TRUE)) { // Intentar crear el directorio
        $form_state->setErrorByName('destination_directory', $this->t('El directorio de destino no existe y no pudo ser creado: @directory. Asegúrate de que la ruta sea válida y los permisos sean correctos.', ['@directory' => $directory]));
      }
    } elseif (!is_writable($directory)) {
      $form_state->setErrorByName('destination_directory', $this->t('El directorio de destino no es escribible: @directory. Por favor, verifica los permisos.', ['@directory' => $directory]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('headless_entity_serializer.settings')
      ->set('entity_types', array_filter($form_state->getValue('entity_types')))
      ->set('destination_directory', $form_state->getValue('destination_directory'))
      ->save();

    // Usa la propiedad inyectada para enviar mensajes.
    $this->messenger->addStatus($this->t('La configuración de serialización de entidades se ha guardado.'));
    parent::submitForm($form, $form_state);
  }

  /**
   * Gets all available entity types for the form options.
   *
   * @return array
   * An array of entity type labels keyed by entity type ID.
   */
  private function getAllContentTypes() {
    $definitions = $this->entityTypeManager->getDefinitions(); // Usamos la propiedad inyectada
    $options = [];
    foreach ($definitions as $key => $value) {
      // Filtra por entidades que son "contenido" y tienen un controlador de almacenamiento.
      // Puedes ajustar esta lógica si necesitas serializar entidades de configuración, por ejemplo.
      if ($value->getHandlerClasses()['storage'] ?? false) {
        $label = $value->getLabel();
        if (in_array($key, $this->entity_types_compatibles)) {
          $label .= " (RECOMENDADO)";
        }
        $options[$key] = $label . " (" . $key . ")";
      }
    }
    // Opcional: Ordenar las opciones alfabéticamente para mejor UX.
    asort($options);
    return $options;
  }
}