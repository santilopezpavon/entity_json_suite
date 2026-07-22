<?php

declare(strict_types=1);

namespace Drupal\entity_json_views\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form to select which REST export View displays to export as JSON.
 */
class ViewsExportSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs a new ViewsExportSettingsForm.
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
    return ['entity_json_views.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_json_views_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('entity_json_views.settings');
    $configured = $config->get('views') ?? [];
    $configuredMap = [];

    foreach ($configured as $item) {
      $configuredMap[$item['view_id'] . ':' . $item['display_id']] = TRUE;
    }

    $restDisplays = $this->getRestExportDisplays();
    if ($restDisplays === []) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('No REST export displays found. Create a View with a REST export display first.') . '</p>',
      ];
      return $form;
    }

    $form['views'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Export'),
        $this->t('View'),
        $this->t('Display'),
        $this->t('Path'),
        $this->t('Last export'),
      ],
      '#empty' => $this->t('No REST export displays found.'),
    ];

    foreach ($restDisplays as $display) {
      $key = $display['view_id'] . ':' . $display['display_id'];
      $checked = isset($configuredMap[$key]);

      $lastExport = '—';
      $uri = 'public://views/' . $display['view_id'] . '/' . $display['display_id'] . '.json';
      if (file_exists($uri)) {
        $timestamp = filemtime($uri);
        if ($timestamp) {
          $lastExport = $this->dateFormatter->format($timestamp, 'short');
        }
      }

      $form['views'][$key] = [
        'export' => [
          '#type' => 'checkbox',
          '#default_value' => $checked,
        ],
        'view' => [
          '#plain_text' => $display['view_label'] . ' (' . $display['view_id'] . ')',
        ],
        'display' => [
          '#plain_text' => $display['display_title'] . ' (' . $display['display_id'] . ')',
        ],
        'path' => [
          '#plain_text' => '/json/views/' . $display['view_id'] . '/' . $display['display_id'],
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
    $values = $form_state->getValue('views');
    $views = [];

    foreach ($values as $key => $item) {
      if (!empty($item['export'])) {
        [$view_id, $display_id] = explode(':', (string) $key, 2);
        $views[] = [
          'view_id' => $view_id,
          'display_id' => $display_id,
        ];
      }
    }

    $this->config('entity_json_views.settings')
      ->set('views', $views)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns all available REST export displays across all views.
   *
   * @return array[]
   *   An array of display info arrays, each with: view_id, view_label,
   *   display_id, display_title.
   */
  protected function getRestExportDisplays(): array {
    $storage = $this->entityTypeManager->getStorage('view');
    $views = $storage->loadMultiple();
    $displays = [];

    foreach ($views as $view_id => $view_entity) {
      foreach ($view_entity->get('display') as $display_id => $display) {
        if (($display['display_plugin'] ?? '') === 'rest_export') {
          $displays[] = [
            'view_id' => $view_id,
            'view_label' => $view_entity->label(),
            'display_id' => $display_id,
            'display_title' => $display['display_title'] ?? $display_id,
          ];
        }
      }
    }

    return $displays;
  }

}
