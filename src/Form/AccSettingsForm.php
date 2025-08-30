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

    // AI API Settings
    $form['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI API Settings'),
      '#open' => TRUE,
      '#description' => $this->t('Configure the AI-powered features including chatbot and analysis services.'),
    ];

    $form['api_settings']['chatbot_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AI Chatbot'),
      '#default_value' => $config->get('chatbot_enabled') ?? FALSE,
      '#description' => $this->t('Enable the AI chatbot feature for providing accessibility solutions.'),
    ];

    $form['api_settings']['chatbot_api_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint'),
      '#default_value' => $config->get('chatbot_api_endpoint') ?: '',
      '#description' => $this->t('Enter the API endpoint URL (Google AI Studio or OpenAI-compatible API).'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_settings']['chatbot_api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('chatbot_api_key') ?: '',
      '#description' => $this->t('Enter your API key for the chatbot service.'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_settings']['chatbot_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model Name'),
      '#default_value' => $config->get('chatbot_model') ?: 'gemini-2.0-flash',
      '#description' => $this->t('Enter the AI model name (e.g., gemini-2.0-flash, gpt-4, claude-3).'),
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_settings']['chatbot_max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Max Tokens'),
      '#default_value' => $config->get('chatbot_max_tokens') ?: 2048,
      '#description' => $this->t('Maximum number of tokens for AI responses. Higher values allow longer, more detailed responses.'),
      '#min' => 500,
      '#max' => 4096,
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_settings']['chatbot_cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL (seconds)'),
      '#default_value' => $config->get('chatbot_cache_ttl') ?: 3600,
      '#description' => $this->t('How long to cache chatbot responses (in seconds). Default is 3600 (1 hour).'),
      '#min' => 300,
      '#max' => 86400,
      '#states' => [
        'visible' => [
          ':input[name="chatbot_enabled"]' => ['checked' => TRUE],
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
    
    // Save AI API settings
    $config->set('chatbot_enabled', (bool) $form_state->getValue('chatbot_enabled'))
           ->set('chatbot_api_endpoint', $form_state->getValue('chatbot_api_endpoint'))
           ->set('chatbot_api_key', $form_state->getValue('chatbot_api_key'))
           ->set('chatbot_model', $form_state->getValue('chatbot_model'))
           ->set('chatbot_max_tokens', (int) $form_state->getValue('chatbot_max_tokens'))
           ->set('chatbot_cache_ttl', (int) $form_state->getValue('chatbot_cache_ttl'))
           ->set('debug_mode', $form_state->getValue('debug_mode'))
           ->save();

    parent::submitForm($form, $form_state);
    
    // Clear cache if AI settings changed
    if ($config->get('chatbot_enabled')) {
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['accessibility_chatbot']);
    }
    
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }
}
