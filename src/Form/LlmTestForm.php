<?php

/**
 * @file
 * Contains \Drupal\accessibility\Form\LlmTestForm.
 *
 * Form for testing LLM integration with local Ollama.
 */

namespace Drupal\accessibility\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Client;

/**
 * Simple form to test LLM integration with local Ollama Mistral.
 */
class LlmTestForm extends FormBase {

  public function getFormId() {
    return 'accessibility_llm_test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Enter a prompt to send to the local Mistral model via Ollama.'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send to Ollama Mistral'),
    ];

    if ($response = $form_state->get('llm_response')) {
      $form['response'] = [
        '#type' => 'textarea',
        '#title' => $this->t('LLM Response'),
        '#default_value' => $response,
        '#attributes' => ['readonly' => 'readonly'],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $prompt = $form_state->getValue('prompt');
    $config = \Drupal::config('accessibility.settings');
    $base_url = rtrim($config->get('llm_api_base_url') ?? 'http://localhost:11434', '/');
    $model = $config->get('llm_model') ?? 'mistral';

    $client = new Client([
      'base_uri' => $base_url,
      'timeout' => 30,
    ]);

    try {
      $response = $client->post('/api/generate', [
        'json' => [
          'model' => $model,
          'prompt' => $prompt,
          'stream' => false,
        ],
      ]);
      $data = json_decode($response->getBody(), TRUE);
      $llm_response = $data['response'] ?? 'No response from Ollama.';
    }
    catch (\Exception $e) {
      $llm_response = 'Error: ' . $e->getMessage();
    }

    $form_state->set('llm_response', $llm_response);
    $form_state->setRebuild();
  }

}
