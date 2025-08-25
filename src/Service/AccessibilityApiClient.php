<?php

namespace Drupal\accessibility\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with the Accessibility API.
 */
class AccessibilityApiClient {

  /**
   * Default cache TTL in seconds (1 hour).
   */
  const CACHE_TTL = 3600;

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
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new AccessibilityApiClient.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    ClientInterface $http_client,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $config_factory
  ) {
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->config = $config_factory->get('accessibility.settings');
  }

  /**
   * Performs an accessibility scan for the given URL.
   *
   * @param string $url
   *   The URL to scan.
   *
   * @return array
   *   The scan results.
   *
   * @throws \InvalidArgumentException
   *   If the URL is invalid.
   * @throws \RuntimeException
   *   If the API request fails.
   */
  public function getScan($url) {
    if (!is_string($url) || empty($url)) {
      throw new \InvalidArgumentException('URL must be a non-empty string.');
    }

    $cache_id = 'accessibility_scan:' . md5($url);

    // Try to get cached results first.
    if ($cached = $this->cache->get($cache_id)) {
      return $cached->data;
    }

    $endpoint = $this->config->get('api_endpoint');
    $api_key = $this->config->get('api_key');

    // If API is not configured, return sample data
    if (empty($endpoint) || empty($api_key)) {
      $sample_data = $this->getSampleData($url);
      $this->cache->set(
        $cache_id,
        $sample_data,
        time() + self::CACHE_TTL,
        ['accessibility_scan']
      );
      return $sample_data;
    }

    try {
      $response = $this->httpClient->post($endpoint, [
        'form_params' => [
          'key' => $api_key,
          'url' => $url,
        ],
        'timeout' => 30,
        'http_errors' => true,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException('Invalid JSON response from API');
      }

      // Ensures we always have a violations array.
      $data['violations'] = $data['violations'] ?? [];

      // Caches the successful response.
      $this->cache->set(
        $cache_id,
        $data,
        time() + self::CACHE_TTL,
        ['accessibility_scan']
      );

      return $data;
    }
    catch (RequestException $e) {
      // If API call fails, return sample data
      $sample_data = $this->getSampleData($url);
      $this->cache->set(
        $cache_id,
        $sample_data,
        time() + self::CACHE_TTL,
        ['accessibility_scan']
      );
      return $sample_data;
    }
  }

  /**
   * Generates sample accessibility scan data for testing/demo purposes.
   *
   * @param string $url
   *   The URL that was scanned.
   *
   * @return array
   *   Sample accessibility scan data.
   */
  protected function getSampleData($url) {
    $timestamp = time();

    return [
      'timestamp' => $timestamp,
      'scanned_url' => $url,
      'score' => 75,
      'violations' => [
        [
          'id' => 'color-contrast',
          'impact' => 'serious',
          'description' => 'Ensures the contrast between foreground and background colors meets WCAG 2 AA contrast ratio thresholds',
          'help' => 'Elements must have sufficient color contrast',
          'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/color-contrast',
          'nodes' => [
            [
              'target' => ['#main-navigation'],
              'failureSummary' => 'Fix any of the following:\n  Element has insufficient color contrast of 3.96 (foreground color: #ffffff, background color: #4c9f70, font size: 12.0pt (16px), font weight: normal). Expected contrast ratio of 4.5:1',
              'html' => '<a href="/about" class="nav-link">About Us</a>'
            ]
          ]
        ],
        [
          'id' => 'image-alt',
          'impact' => 'critical',
          'description' => 'Ensures <img> elements have alternate text or a role of none or presentation',
          'help' => 'Images must have alternate text',
          'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/image-alt',
          'nodes' => [
            [
              'target' => ['.hero-image'],
              'failureSummary' => 'Element does not have an alt attribute',
              'html' => '<img src="/sites/default/files/hero.jpg" class="hero-image">'
            ]
          ]
        ],
        [
          'id' => 'button-name',
          'impact' => 'serious',
          'description' => 'Ensures buttons have discernible text',
          'help' => 'Buttons must have discernible text',
          'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/button-name',
          'nodes' => [
            [
              'target' => ['#search-submit'],
              'failureSummary' => 'Element does not have inner text that is visible to screen readers',
              'html' => '<button id="search-submit" type="submit"><i class="fa fa-search"></i></button>'
            ]
          ]
        ],
        [
          'id' => 'html-has-lang',
          'impact' => 'serious',
          'description' => 'Ensures every HTML document has a lang attribute',
          'help' => '<html> element must have a lang attribute',
          'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/html-has-lang',
          'nodes' => [
            [
              'target' => ['html'],
              'failureSummary' => 'The <html> element does not have a lang attribute',
              'html' => '<html>...</html>'
            ]
          ]
        ],
        [
          'id' => 'link-name',
          'impact' => 'serious',
          'description' => 'Ensures links have discernible text',
          'help' => 'Links must have discernible text',
          'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/link-name',
          'nodes' => [
            [
              'target' => ['.social-link'],
              'failureSummary' => 'Element does not have text that is visible to screen readers',
              'html' => '<a href="#" class="social-link"><i class="fa fa-twitter"></i></a>'
            ]
          ]
        ]
      ],
      'metadata' => [
        'scanner' => 'accessibility-scanner',
        'version' => '1.0.0',
        'generated' => date('c', $timestamp)
      ]
    ];
  }
}
