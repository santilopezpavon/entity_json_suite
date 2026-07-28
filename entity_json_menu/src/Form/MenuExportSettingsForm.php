<?php

declare(strict_types=1);

namespace Drupal\entity_json_menu\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form to select which menus to export as JSON.
 */
class MenuExportSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new MenuExportSettingsForm.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['entity_json_menu.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_json_menu_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('entity_json_menu.settings');
    $configured = $config->get('menus') ?? [];
    $configuredMap = array_flip($configured);

    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    if ($menus === []) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('No menus found. Create a menu first.') . '</p>',
      ];
      return $form;
    }

    $form['menus'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Export'),
        $this->t('Name'),
        $this->t('Machine name'),
        $this->t('Description'),
        $this->t('Path'),
        $this->t('Last export'),
      ],
      '#empty' => $this->t('No menus found.'),
    ];

    foreach ($menus as $menu_id => $menu_entity) {
      $checked = isset($configuredMap[$menu_id]);

      $lastExport = '---';
      $uri = 'public://menus/' . $menu_id . '.json';
      if (file_exists($uri)) {
        $timestamp = filemtime($uri);
        if ($timestamp) {
          $lastExport = $this->dateFormatter->format($timestamp, 'short');
        }
      }

      $form['menus'][$menu_id] = [
        'export' => [
          '#type' => 'checkbox',
          '#default_value' => $checked,
        ],
        'name' => [
          '#plain_text' => (string) $menu_entity->label(),
        ],
        'machine_name' => [
          '#plain_text' => $menu_id,
        ],
        'description' => [
          '#plain_text' => (string) $menu_entity->getDescription(),
        ],
        'path' => [
          '#plain_text' => '/json/menus/' . $menu_id,
        ],
        'last_export' => [
          '#plain_text' => $lastExport,
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('menus');
    $menus = [];

    foreach ($values as $menu_id => $item) {
      if (!empty($item['export'])) {
        $menus[] = $menu_id;
      }
    }

    $this->config('entity_json_menu.settings')
      ->set('menus', $menus)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
