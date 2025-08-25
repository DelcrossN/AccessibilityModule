<?php

namespace Drupal\accessibility\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure LLM settings for the accessibility module (Ollama local).
 */
class LlmSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'accessibility_llm_settings';
  }

  protected function getEditableConfigNames() {
    return ['accessibility.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('accessibility.settings');

    $form['llm_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable LLM Analysis'),
      '#description' => $this->t('Enable AI-powered accessibility analysis using a local LLM (Ollama).'),
      '#default_value' => $config->get('llm_enabled') ?? FALSE,
    ];

    $form['api_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Ollama API Connection Settings'),
      '#states' => [
        'visible' => [
          ':input[name="llm_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_settings']['llm_api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama API Base URL'),
      '#description' => $this->t('The base URL for your local Ollama API. Default: %url', ['%url' => 'http://localhost:11434']),
      '#default_value' => $config->get('llm_api_base_url') ?? 'http://localhost:11434',
      '#required' => TRUE,
    ];

    $form['api_settings']['llm_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama Model Name'),
      '#description' => $this->t('The model to use (e.g., mistral, llama3, etc).'),
      '#default_value' => $config->get('llm_model') ?? 'mistral',
      '#required' => TRUE,
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debug Mode'),
      '#description' => $this->t('Show additional debug information and raw API responses on test pages.'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('accessibility.settings');
    $config
      ->set('llm_enabled', $form_state->getValue('llm_enabled'))
      ->set('llm_api_base_url', $form_state->getValue('llm_api_base_url'))
      ->set('llm_model', $form_state->getValue('llm_model'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
