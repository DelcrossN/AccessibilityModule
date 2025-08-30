<?php

/**
 * @file
 * Contains the settings for administering the Accessibility module.
 */

namespace Drupal\accessibility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Accessibility settings for this site.
 */
class AccSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['accessibility.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'accessibility_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('accessibility.settings');

    $form['description'] = [
      '#markup' => '<div class="messages messages--status"><strong>Accessibility Settings</strong><br>Configure the accessibility features for your site.</div>',
    ];

    // API Settings
    $form['api_section'] = [
      '#type' => 'details',
      '#title' => $this->t('API Settings'),
      '#open' => TRUE,
    ];

    $form['api_section']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key') ?: '',
      '#description' => $this->t('Enter your API key for the accessibility service.'),
      '#required' => TRUE,
    ];

    $form['api_section']['api_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint'),
      '#default_value' => $config->get('api_endpoint') ?: 'https://api.accessibility.com/v1',
      '#description' => $this->t('Enter the base URL for the accessibility API.'),
      '#required' => TRUE,
    ];

    // Advanced Settings
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug mode'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
      '#description' => $this->t('Show debug information in the logs.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('accessibility.settings');
    
    // Save API key and basic settings
    $config->set('api_key', $form_state->getValue('api_key'))
           ->set('api_endpoint', $form_state->getValue('api_endpoint'))
           ->set('debug_mode', $form_state->getValue('debug_mode'))
           ->save();

    parent::submitForm($form, $form_state);
    
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }
}
