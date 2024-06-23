<?php

namespace Drupal\entity_json_speed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class SettingsForm extends ConfigFormBase {

  private $entity_types_compatibles = ["node", "path_alias", "media", "paragraph", "menu", "taxonomy_term"];

  protected function getEditableConfigNames() {
    return [
      'entity_json_speed.settings',
    ];
  }

  public function getFormId() {
    return 'entity_json_speed.settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('entity_json_speed.settings');    
    
    $content_types = $this->getAllContentTypes();
    $form['base_group'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Base Configuration'),
    );   
    $default_content_type = $config->get('content_types');
    if($default_content_type == null) {
      $default_content_type = [];
    }
    $form['base_group']["content_types"] = [
        '#type' => 'checkboxes',
        '#title' => t('Entity Types'),
        '#options' => $content_types,
        '#description' => t('Choose the entity types you want to include in your custom content in JSON format.'),
        '#default_value' => $default_content_type,
    ];

    return parent::buildForm($form, $form_state);
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('entity_json_speed.settings');
    $config->set('content_types', $form_state->getValue('content_types'));

    $nonAssociativeArray = [];
    foreach ($form_state->getValue('content_types') as $key => $value) {
        if ($value !== 0) {
            $nonAssociativeArray[] = $key;
        }
    }  
    $config->set('content_types_array', $nonAssociativeArray);    
    $config->save();
    parent::submitForm($form, $form_state);
  }

  
  private function getAllContentTypes() {
    $definitions = \Drupal::entityTypeManager()->getDefinitions();
    $options = [];
    foreach ($definitions as $key => $value) {
      $label = $value->getLabel() ;
      if(in_array($key, $this->entity_types_compatibles)) {
        $label .= " (RECOMENDED) ";
      }
     $options[$key] = $label . " (" . $key . ")";

    }
    return $options;

  }

}
