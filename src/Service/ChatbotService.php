<?php

/**
 * @file
 * Contains \Drupal\accessibility\Service\ChatbotService.
 *
 * Provides chatbot functionality for accessibility assistance.
 */

namespace Drupal\accessibility\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
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
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a new ChatbotService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->cache = $cache;
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
    $max_tokens = $config->get('chatbot_max_tokens') ?: 5000;

    if (!$api_endpoint || !$api_key) {
      return [
        'success' => FALSE,
        'error' => 'Chatbot API endpoint or key not configured.',
      ];
    }

    // Generate cache key based on inputs
    $cache_key = $this->generateCacheKey($violation_id, $violation_description, $user_question, $model);
    $cache_ttl = $config->get('chatbot_cache_ttl') ?: 3600; // Default 1 hour

    // Check cache first
    $cached_result = $this->cache->get($cache_key);
    if ($cached_result && $cached_result->valid) {
      $this->loggerFactory->get('accessibility')->info('Chatbot response served from cache for violation: @violation', [
        '@violation' => $violation_id,
      ]);
      $cached_data = $cached_result->data;
      $cached_data['cached'] = TRUE;
      return $cached_data;
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
              'maxOutputTokens' => $max_tokens,
            ],
          ],
          'timeout' => 60,
        ]);

        $data = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
          $result = [
            'success' => TRUE,
            'solution' => $data['candidates'][0]['content']['parts'][0]['text'],
            'model_used' => $model,
          ];

          // Cache the successful result
          $this->cache->set($cache_key, $result, time() + $cache_ttl, ['accessibility_chatbot']);
          return $result;
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
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
          ],
          'timeout' => 60,
        ]);

        $data = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($data['choices'][0]['message']['content'])) {
          $result = [
            'success' => TRUE,
            'solution' => $data['choices'][0]['message']['content'],
            'model_used' => $model,
          ];

          // Cache the successful result
          $this->cache->set($cache_key, $result, time() + $cache_ttl, ['accessibility_chatbot']);
          return $result;
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
    $base_prompt = "You are an expert accessibility consultant helping developers fix WCAG compliance issues.\n\n";
    $base_prompt .= "## ACCESSIBILITY VIOLATION DETAILS\n\n";
    $base_prompt .= "**Violation ID:** `{$violation_id}`\n";
    $base_prompt .= "**Issue Description:** {$violation_description}\n\n";

    if (!empty($user_question)) {
      $base_prompt .= "## USER'S SPECIFIC QUESTION\n\n";
      $base_prompt .= "{$user_question}\n\n";
    }

    $base_prompt .= "## REQUIRED RESPONSE FORMAT\n\n";
    $base_prompt .= "Provide a detailed solution using the following structure:\n\n";
    $base_prompt .= "### Why This Matters\n";
    $base_prompt .= "- Explain the accessibility impact and affected users\n";
    $base_prompt .= "- Reference relevant WCAG guidelines and success criteria\n";
    $base_prompt .= "- Describe potential legal and practical consequences\n\n";
    $base_prompt .= "### How to Fix It\n";
    $base_prompt .= "- Provide specific, actionable code examples\n";
    $base_prompt .= "- Show both the problematic code and the corrected version\n";
    $base_prompt .= "- Include language/framework-specific implementations when applicable\n";
    $base_prompt .= "- Use proper syntax highlighting with language identifiers\n\n";
    $base_prompt .= "### Testing Your Fix\n";
    $base_prompt .= "- Provide manual testing steps\n";
    $base_prompt .= "- Suggest automated testing tools or methods\n";
    $base_prompt .= "- Include verification criteria\n\n";
    $base_prompt .= "### Best Practices & Prevention\n";
    $base_prompt .= "- Share development workflow improvements\n";
    $base_prompt .= "- Recommend tools, linters, or frameworks that help prevent this issue\n";
    $base_prompt .= "- Suggest related accessibility considerations\n\n";
    $base_prompt .= "## RESPONSE GUIDELINES\n\n";
    $base_prompt .= "- Use proper markdown formatting with headers, code blocks, and lists\n";
    $base_prompt .= "- Include specific code examples with language identifiers (```html, ```css, ```javascript, etc.)\n";
    $base_prompt .= "- Be concise but thorough - focus on actionable solutions\n";
    $base_prompt .= "- Consider different implementation scenarios (static sites, frameworks, CMS)\n";
    $base_prompt .= "- Always provide context about why the solution works\n";
    $base_prompt .= "- If multiple solutions exist, explain the trade-offs\n\n";
    $base_prompt .= "## TECHNICAL CONTEXT\n\n";
    $base_prompt .= "- This is for a web-based platform (likely Drupal or similar CMS)\n";
    $base_prompt .= "- Solutions should work across modern browsers\n";
    $base_prompt .= "- Consider both server-side and client-side implementations\n";
    $base_prompt .= "- Account for progressive enhancement and graceful degradation\n\n";
    $base_prompt .= "Remember: Your goal is to provide immediately actionable solutions that developers can implement confidently, while building their understanding of accessibility principles.";

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

  /**
   * Generate a cache key based on the request parameters.
   *
   * @param string $violation_id
   *   The violation ID.
   * @param string $violation_description
   *   The violation description.
   * @param string $user_question
   *   The user's specific question.
   * @param string $model
   *   The AI model being used.
   *
   * @return string
   *   A unique cache key.
   */
  private function generateCacheKey($violation_id, $violation_description, $user_question, $model) {
    // Create a hash of the input parameters to generate a unique key
    $key_data = [
      'violation_id' => $violation_id,
      'violation_description' => $violation_description,
      'user_question' => $user_question,
      'model' => $model,
    ];
    $key_string = json_encode($key_data);
    $hash = hash('sha256', $key_string);
    return 'accessibility_chatbot:' . substr($hash, 0, 32);
  }

  /**
   * Clear all cached chatbot responses.
   *
   * @return bool
   *   TRUE if cache was cleared successfully, FALSE otherwise.
   */
  public function clearCache() {
    try {
      // Clear all cached chatbot responses using cache tags
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['accessibility_chatbot']);
      $this->loggerFactory->get('accessibility')->info('Chatbot cache cleared successfully');
      return TRUE;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('accessibility')->error('Failed to clear chatbot cache: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Get cache statistics.
   *
   * @return array
   *   Cache statistics including hit rates and usage.
   */
  public function getCacheStats() {
    // This is a simple implementation - in a real-world scenario,
    // you might want to track more detailed statistics
    return [
      'cache_enabled' => TRUE,
      'cache_backend' => get_class($this->cache),
      'note' => 'Detailed cache statistics require additional monitoring implementation',
    ];
  }

}
