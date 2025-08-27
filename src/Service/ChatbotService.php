<?php

namespace Drupal\accessibility\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for handling chatbot API communication.
 */
class ChatbotService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new ChatbotService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get accessibility solution suggestions from the chatbot.
   *
   * @param string $violation_id
   *   The violation ID (e.g., 'link-in-text-block').
   * @param string $violation_description
   *   The violation description.
   * @param string $user_question
   *   The user's specific question or request for help.
   *
   * @return array
   *   Response containing the chatbot's solution or error message.
   */
  public function getAccessibilitySolution($violation_id, $violation_description, $user_question = '') {
    $config = $this->configFactory->get('accessibility.settings');
    
    if (!$config->get('chatbot_enabled')) {
      return [
        'success' => FALSE,
        'error' => 'Chatbot is not enabled.',
      ];
    }

    $api_endpoint = $config->get('chatbot_api_endpoint');
    $api_key = $config->get('chatbot_api_key');
    $model = $config->get('chatbot_model') ?: 'gemini-2.0-flash';

    if (!$api_endpoint || !$api_key) {
      return [
        'success' => FALSE,
        'error' => 'Chatbot API endpoint or key not configured.',
      ];
    }

    // Construct the prompt for the chatbot
    $prompt = $this->buildAccessibilityPrompt($violation_id, $violation_description, $user_question);

    // Detect if this is a Google AI Studio endpoint
    $is_google_ai = strpos($api_endpoint, 'generativelanguage.googleapis.com') !== FALSE;

    try {
      if ($is_google_ai) {
        // Google AI Studio API format
        $response = $this->httpClient->post($api_endpoint . '?key=' . $api_key, [
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'contents' => [
              [
                'parts' => [
                  [
                    'text' => 'You are an accessibility expert assistant. Provide practical, actionable solutions for web accessibility violations. Focus on code examples and clear explanations.' . "\n\n" . $prompt,
                  ],
                ],
              ],
            ],
            'generationConfig' => [
              'temperature' => 0.7,
              'topK' => 40,
              'topP' => 0.95,
              'maxOutputTokens' => 500,
            ],
          ],
          'timeout' => 30,
        ]);

        $data = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
          return [
            'success' => TRUE,
            'solution' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model_used' => $model,
          ];
        }
        else {
          $this->loggerFactory->get('accessibility')->error('Invalid Google AI API response: @response', [
            '@response' => json_encode($data),
          ]);
          return [
            'success' => FALSE,
            'error' => 'Invalid response from Google AI API.',
          ];
        }
      }
      else {
        // OpenAI-compatible API format
        $response = $this->httpClient->post($api_endpoint, [
          'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'model' => $model,
            'messages' => [
              [
                'role' => 'system',
                'content' => 'You are an accessibility expert assistant. Provide practical, actionable solutions for web accessibility violations. Focus on code examples and clear explanations.',
              ],
              [
                'role' => 'user',
                'content' => $prompt,
              ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
          ],
          'timeout' => 30,
        ]);

        $data = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($data['choices'][0]['message']['content'])) {
          return [
            'success' => TRUE,
            'solution' => $data['choices'][0]['message']['content'],
            'model_used' => $model,
          ];
        }
        else {
          $this->loggerFactory->get('accessibility')->error('Invalid OpenAI API response: @response', [
            '@response' => json_encode($data),
          ]);
          return [
            'success' => FALSE,
            'error' => 'Invalid response from chatbot API.',
          ];
        }
      }
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('accessibility')->error('Chatbot API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'Failed to connect to chatbot API: ' . $e->getMessage(),
      ];
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('accessibility')->error('Unexpected error in chatbot service: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'error' => 'An unexpected error occurred.',
      ];
    }
  }

  /**
   * Build the accessibility prompt for the chatbot.
   *
   * @param string $violation_id
   *   The violation ID.
   * @param string $violation_description
   *   The violation description.
   * @param string $user_question
   *   The user's specific question.
   *
   * @return string
   *   The formatted prompt.
   */
  private function buildAccessibilityPrompt($violation_id, $violation_description, $user_question) {
    $base_prompt = "I have an accessibility violation on my website:\n\n";
    $base_prompt .= "Violation: {$violation_id}\n";
    $base_prompt .= "Description: {$violation_description}\n\n";

    if (!empty($user_question)) {
      $base_prompt .= "Specific question: {$user_question}\n\n";
    }

    $base_prompt .= "Please provide:\n";
    $base_prompt .= "1. A clear explanation of why this is an accessibility issue\n";
    $base_prompt .= "2. Specific code examples showing how to fix it\n";
    $base_prompt .= "3. Best practices to prevent this issue in the future\n\n";
    $base_prompt .= "Keep your response concise but practical, focusing on actionable solutions.";

    return $base_prompt;
  }

  /**
   * Check if the chatbot service is properly configured.
   *
   * @return bool
   *   TRUE if the service is configured, FALSE otherwise.
   */
  public function isConfigured() {
    $config = $this->configFactory->get('accessibility.settings');
    return $config->get('chatbot_enabled') && 
           $config->get('chatbot_api_endpoint') && 
           $config->get('chatbot_api_key');
  }

}
