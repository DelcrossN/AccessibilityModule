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

    // Scan Settings
    $form['scan_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Scan Settings'),
      '#open' => TRUE,
    ];

    $form['scan_settings']['auto_scan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic scanning'),
      '#default_value' => $config->get('auto_scan') ?? TRUE,
      '#description' => $this->t('Automatically scan pages for accessibility issues.'),
    ];

    $form['scan_settings']['scan_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Scan Frequency'),
      '#options' => [
        '3600' => $this->t('Hourly'),
        '86400' => $this->t('Daily'),
        '604800' => $this->t('Weekly'),
      ],
      '#default_value' => $config->get('scan_frequency') ?: '86400',
      '#description' => $this->t('How often to perform accessibility scans.'),
      '#states' => [
        'visible' => [
          ':input[name="auto_scan"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Display Settings
    $form['display_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Settings'),
      '#open' => FALSE,
    ];

    $form['display_settings']['show_widget'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show accessibility widget'),
      '#default_value' => $config->get('show_widget') ?? TRUE,
      '#description' => $this->t('Display the accessibility widget on the site.'),
    ];

    $form['display_settings']['widget_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget Position'),
      '#options' => [
        'left' => $this->t('Left'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $config->get('widget_position') ?: 'right',
      '#description' => $this->t('Position of the accessibility widget on the screen.'),
      '#states' => [
        'visible' => [
          ':input[name="show_widget"]' => ['checked' => TRUE],
        ],
      ],
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
           ->set('show_widget', $form_state->getValue('show_widget'))
           ->set('widget_position', $form_state->getValue('widget_position'))
           ->set('debug_mode', $form_state->getValue('debug_mode'));
    
    // Save LLM settings
    $config->set('llm_enabled', (bool) $form_state->getValue('llm_enabled'))
           ->set('llm_openrouter_key', $form_state->getValue('llm_openrouter_key'))
           ->set('llm_model', $form_state->getValue('llm_model'))
           ->set('llm_temperature', (float) $form_state->getValue('llm_temperature'))
           ->set('llm_max_tokens', (int) $form_state->getValue('llm_max_tokens'))
           ->set('llm_cache_ttl', (int) $form_state->getValue('llm_cache_ttl'))
           ->save();

    parent::submitForm($form, $form_state);
    
    // Clear cache if LLM settings changed
    if ($config->get('llm_enabled')) {
      \Drupal::cache()->invalidateTags(['llm_analysis']);
    }
    
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }
}
