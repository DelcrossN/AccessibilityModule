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

    // Chatbot Settings
    $form['chatbot_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Chatbot Settings'),
      '#open' => FALSE,
    ];

    $form['chatbot_settings']['chatbot_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Chatbot for Accessibility Help'),
      '#default_value' => $config->get('chatbot_enabled') ?? FALSE,
      '#description' => $this->t('Enable chatbot interface below each violation to provide AI-powered accessibility solutions.'),
    ];

    $form['chatbot_settings']['chatbot_api_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Chatbot API Endpoint'),
      '#default_value' => $config->get('chatbot_api_endpoint') ?: '',
      '#description' => $this->t('Enter the API endpoint URL for the chatbot service.<br><strong>Google AI Studio (Gemini 2.0 Flash):</strong> https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent<br><strong>OpenAI:</strong> https://api.openai.com/v1/chat/completions'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['chatbot_settings']['chatbot_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chatbot API Key'),
      '#default_value' => $config->get('chatbot_api_key') ?: '',
      '#description' => $this->t('Enter the API key for authenticating with the chatbot service.<br><strong>Google AI Studio:</strong> Get your API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio API Keys</a><br><strong>OpenAI:</strong> Get your API key from OpenAI dashboard'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['chatbot_settings']['chatbot_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chatbot Model'),
      '#default_value' => $config->get('chatbot_model') ?: 'gemini-2.0-flash-exp',
      '#description' => $this->t('Specify the AI model to use for chatbot responses.<br><strong>Google AI Studio:</strong> gemini-2.0-flash-exp, gemini-1.5-flash, gemini-1.5-pro<br><strong>OpenAI:</strong> gpt-3.5-turbo, gpt-4, gpt-4-turbo'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
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
    
    // Save scan settings
    $config->set('auto_scan', $form_state->getValue('auto_scan'))
           ->set('scan_frequency', $form_state->getValue('scan_frequency'))
           ->set('api_endpoint', $form_state->getValue('api_endpoint'));
    
    // Save chatbot settings
    $config->set('chatbot_enabled', (bool) $form_state->getValue('chatbot_enabled'))
           ->set('chatbot_api_endpoint', $form_state->getValue('chatbot_api_endpoint'))
           ->set('chatbot_api_key', $form_state->getValue('chatbot_api_key'))
           ->set('chatbot_model', $form_state->getValue('chatbot_model'))
           ->save();

    parent::submitForm($form, $form_state);
    
    // Clear cache if LLM settings changed
    if ($config->get('llm_enabled')) {
      \Drupal::cache()->invalidateTags(['llm_analysis']);
    }
    
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }
}
