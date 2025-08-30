<?php

/**
 * @file
 * Contains \Drupal\accessibility\Service\LlmAnalysisService.
 *
 * Service for LLM-based accessibility analysis.
 */

namespace Drupal\accessibility\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with LLM APIs via OpenRouter.
 */
class LlmAnalysisService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new LlmAnalysisService.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface $http_client,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('accessibility_llm');
  }

  /**
   * Analyze accessibility issues using LLM.
   *
   * @param string $html_content
   *   The HTML content to analyze.
   * @param array $existing_issues
   *   Existing accessibility issues found by other tools.
   *
   * @return array
   *   The analysis results.
   */
  public function analyzeAccessibility(string $html_content, array $existing_issues = []): array {
    $config = $this->configFactory->get('accessibility.settings');
    
    // Check if LLM analysis is enabled
    if (!$config->get('llm_enabled') || !$config->get('llm_openrouter_key')) {
      return [];
    }

    // Generate a cache ID based on the content and existing issues
    $cache_id = 'llm_analysis:' . md5($html_content . serialize($existing_issues));
    
    // Try to get cached result
    if ($cache = $this->cache->get($cache_id)) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->post('https://openrouter.ai/api/v1/chat/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $config->get('llm_openrouter_key'),
          'Content-Type' => 'application/json',
          'HTTP-Referer' => \Drupal::request()->getSchemeAndHttpHost(),
        ],
        'json' => [
          'model' => $config->get('llm_model') ?: 'mistralai/mistral-7b-instruct:free',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'You are an expert in web accessibility. Analyze the following HTML content and accessibility issues. ' .
                           'Provide specific, actionable recommendations to fix the issues. ' .
                           'Format your response as a JSON object with "issues" and "recommendations" arrays.'
            ],
            [
              'role' => 'user',
              'content' => json_encode([
                'html_content' => mb_substr($html_content, 0, 10000), // Limit content size
                'existing_issues' => $existing_issues,
              ], JSON_PRETTY_PRINT)
            ]
          ],
          'temperature' => (float) ($config->get('llm_temperature') ?: 0.7),
          'max_tokens' => (int) ($config->get('llm_max_tokens') ?: 1000),
        ],
      ]);

      $result = json_decode((string) $response->getBody(), TRUE);
      $analysis = $this->processAnalysis($result);
      
      // Cache the result
      $this->cache->set(
        $cache_id,
        $analysis,
        time() + ($config->get('llm_cache_ttl') ?: 86400),
        ['llm_analysis']
      );

      return $analysis;
    }
    catch (\Exception $e) {
      $this->logger->error('LLM analysis failed: @error', ['@error' => $e->getMessage()]);
      return [
        'error' => $e->getMessage(),
        'issues' => [],
        'recommendations' => []
      ];
    }
  }

  /**
   * Process the raw LLM response into a structured format.
   *
   * @param array $response
   *   The raw response from the LLM API.
   *
   * @return array
   *   The processed analysis.
   */
  protected function processAnalysis(array $response): array {
    // Default structure
    $result = [
      'issues' => [],
      'recommendations' => [],
      'raw' => $response
    ];

    try {
      // Try to extract JSON from the response
      $content = $response['choices'][0]['message']['content'] ?? '';
      
      // First try to parse as JSON
      $json_start = strpos($content, '{');
      $json_end = strrpos($content, '}') + 1;
      
      if ($json_start !== FALSE && $json_end !== FALSE) {
        $json_content = substr($content, $json_start, $json_end - $json_start);
        $parsed = json_decode($json_content, TRUE);
        
        if (json_last_error() === JSON_ERROR_NONE) {
          $result['issues'] = $parsed['issues'] ?? [];
          $result['recommendations'] = $parsed['recommendations'] ?? [];
          return $result;
        }
      }
      
      // Fallback: Try to extract lists if JSON parsing fails
      $lines = explode("\n", $content);
      $current_section = null;
      
      foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (stripos($line, 'issues:') !== FALSE || stripos($line, 'recommendations:') !== FALSE) {
          $current_section = strtolower(trim(str_replace(':', '', $line)));
          continue;
        }
        
        if ($current_section && (str_starts_with($line, '- ') || str_starts_with($line, '* '))) {
          $item = trim(substr($line, 1));
          if (!empty($item)) {
            $result[$current_section][] = $item;
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to process LLM response: @error', ['@error' => $e->getMessage()]);
    }
    
    return $result;
  }
}
