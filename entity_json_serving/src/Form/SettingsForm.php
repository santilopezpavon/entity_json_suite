<?php

declare(strict_types=1);

namespace Drupal\entity_json_serving\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for entity_json_serving.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['entity_json_serving.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_json_serving_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['routes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Available routes'),
    ];

    $form['routes']['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Method'), $this->t('Path'), $this->t('Description')],
      '#rows' => [
        ['GET', '/json/{entity_type}/{entity_id}/{langcode}', $this->t('Serve a single entity translation JSON')],
        ['GET', '/json/path/{alias}', $this->t('Resolve alias and serve entity JSON')],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('entity_json_serving.settings')->save();
    parent::submitForm($form, $form_state);
  }

}
