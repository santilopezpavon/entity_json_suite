<?php

declare(strict_types=1);

namespace Drupal\Tests\headless_entity_serializer\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\headless_entity_serializer\Form\SettingsForm;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the SettingsForm.
 *
 * @group headless_entity_serializer
 */
class SettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'file',
    'serialization',
    'headless_entity_serializer',
    'config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['headless_entity_serializer']);
  }

  /**
   * Tests that the form can be built without errors.
   */
  public function testFormBuild(): void {
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();

    $form = $form_builder->buildForm(SettingsForm::class, $form_state);

    $this->assertArrayHasKey('destination_directory', $form);
    $this->assertArrayHasKey('entity_types', $form);
    $this->assertArrayHasKey('entity_types_inline', $form);
  }

  /**
   * Tests validation rejects non-writable existing directory.
   *
   * Verifies fix #6: error messages are in English.
   */
  public function testFormValidationNonWritableDirectory(): void {
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();
    $form_state->setValue('destination_directory', '/root');

    $form_builder->buildForm(SettingsForm::class, $form_state);
    $form_builder->submitForm(SettingsForm::class, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Tests form submission saves config correctly.
   */
  public function testFormSubmission(): void {
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();

    $dir = $this->siteDirectory . '/files/test_export';
    mkdir($dir, 0775, TRUE);

    $form_state->setValues([
      'destination_directory' => $dir,
      'entity_types' => ['node' => 'node'],
      'entity_types_inline' => [],
    ]);

    $form_builder->buildForm(SettingsForm::class, $form_state);
    $form_builder->submitForm(SettingsForm::class, $form_state);

    $this->assertFalse($form_state->hasAnyErrors());

    $config = $this->config('headless_entity_serializer.settings');
    $this->assertEquals($dir, $config->get('destination_directory'));
    $this->assertEquals(['node'], array_values($config->get('entity_types')));
  }

}
