<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\accessibility\Service\AccessibilityApiClient;
use Drupal\accessibility\Service\LlmAnalysisService;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Controller for handling accessibility dashboard and reporting.
 *
 * This controller manages the accessibility dashboard, statistics, and
 * reporting functionality.
 */
class AccessibilityController extends ControllerBase {

  /**
   * The accessibility API client service.
   *
   * @var \Drupal\accessibility\Service\AccessibilityApiClient
   */
  protected $apiClient;

  /**
   * The LLM analysis service.
   *
   * @var \Drupal\accessibility\Service\LlmAnalysisService
   */
  protected $llmService;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new AccessibilityController.
   *
   * @param \Drupal\accessibility\Service\AccessibilityApiClient $api_client
   *   The accessibility API client.
   * @param \Drupal\accessibility\Service\LlmAnalysisService $llm_service
   *   The LLM analysis service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(AccessibilityApiClient $api_client, LlmAnalysisService $llm_service, ClientInterface $http_client) {
    $this->apiClient = $api_client;
    $this->llmService = $llm_service;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('accessibility.api_client'),
      $container->get('accessibility.llm_service'),
      $container->get('http_client')
    );
  }

  /**
   * Displays the accessibility dashboard.
   *
   * @return array
   *   A render array for the dashboard page.
   */
  public function dashboard() {
    return [
      '#theme' => 'accessibility_dashboard',
      '#dashboard_url' => Url::fromRoute('accessibility.dashboard')->toString(),
      '#config_url' => Url::fromRoute('accessibility.settings')->toString(),
      '#stats_url' => Url::fromRoute('accessibility.stats')->toString(),
      '#report_url' => Url::fromRoute('accessibility.report', ['path' => '/'])->toString(),
      '#attached' => [
        'library' => [
          'accessibility/axe_scanner',
          'accessibility/chartjs',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Displays an accessibility report for a specific path.
   *
   * @param string $path
   *   The path to generate the report for.
   *
   * @return array
   *   A render array for the report page.
   */
  public function report($path = '/') {
    $build = [];
    $config = $this->config('accessibility.settings');
    $path = '/' . ltrim($path, '/');
    $url = Url::fromUserInput($path, ['absolute' => TRUE])->toString();
    $build['#title'] = $this->t('Accessibility Report for @path', ['@path' => $path]);

    try {
      $response = $this->httpClient->get($url);
      $html = (string) $response->getBody();
      $violations = $this->apiClient->scanHtml($html, $url);
      $llm_analysis = [];

      if ($config->get('llm_enabled')) {
        try {
          $llm_analysis = $this->llmService->analyzeAccessibility($html);
        }
        catch (\Exception $e) {
          $this->getLogger('accessibility')->error('LLM analysis failed: @error', ['@error' => $e->getMessage()]);
          $this->messenger()->addError($this->t('LLM analysis failed. Check logs for details.'));
        }
      }

      $build['report'] = [
        '#theme' => 'accessibility_report',
        '#violations' => $violations,
        '#llm_analysis' => $llm_analysis,
        '#url' => $url,
        '#attached' => ['library' => ['accessibility/report']],
        '#cache' => ['contexts' => ['url.path'], 'tags' => ['accessibility:report']],
      ];
    }
    catch (RequestException $e) {
      $this->messenger()->addError($this->t('Failed to retrieve page content for report: @error', ['@error' => $e->getMessage()]));
      $this->getLogger('accessibility')->error('Failed to retrieve page content for report: @error', ['@error' => $e->getMessage()]);
    }

    return $build;
  }

  /**
   * Displays accessibility statistics.
   *
   * @return array
   *   A render array for the statistics page.
   */
  public function stats() {
    // @TODO: Replace sample data with data from a database or API.
    $stats = [
      'total_issues' => 42,
      'fixed_issues' => 18,
      'high_priority' => 9,
      'pages_scanned' => 24,
    ];

    return [
      '#theme' => 'accessibility_stats',
      '#stats' => $stats,
      '#attached' => ['library' => ['accessibility/stats']],
      '#cache' => ['max-age' => 0, 'tags' => ['accessibility_stats']],
    ];
  }

  /**
   * Generates an accessibility report for a given URL with optional AI analysis.
   *
   * @param string $url
   *   The URL to scan.
   * @param bool $use_ai
   *   Whether to include AI analysis.
   *
   * @return array
   *   A render array for the report page.
   */
  public function generateReport($url = NULL, $use_ai = FALSE) {
    if (empty($url)) {
      $this->messenger()->addError($this->t('No URL was provided to generate a report.'));
      return [];
    }

    $full_url = $this->apiClient->getFullUrl($url);
    $config = $this->config('accessibility.settings');

    try {
      // Fetch the page content only ONCE using the injected client.
      $response = $this->httpClient->get($full_url);
      $html_content = (string) $response->getBody();

      // This assumes your API client can scan raw HTML. If it must take a URL,
      // this logic still correctly avoids a second fetch for the LLM service.
      $report = $this->apiClient->scanHtml($html_content, $full_url);

      $ai_analysis = [];
      if ($use_ai && $config->get('llm_enabled')) {
        try {
          $existing_issues = [];
          if (!empty($report['violations'])) {
            foreach ($report['violations'] as $violation) {
              $existing_issues[] = [
                'type' => $violation['id'] ?? 'unknown',
                'description' => $violation['description'] ?? '',
                'impact' => $violation['impact'] ?? 'moderate',
                'nodes' => count($violation['nodes'] ?? []),
              ];
            }
          }

          // Pass the already-fetched HTML to the LLM service.
          $ai_analysis = $this->llmService->analyzeAccessibility($html_content, $existing_issues);
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('The AI analysis could not be completed. Please check the logs for details.'));
          $this->getLogger('accessibility_llm')->error('AI analysis failed: @error', ['@error' => $e->getMessage()]);
        }
      }

      return [
        '#theme' => 'accessibility_report',
        '#report' => $report,
        '#ai_analysis' => $ai_analysis,
        '#scanned_url' => $full_url,
        '#use_ai' => $use_ai,
        '#attached' => [
          'library' => ['accessibility/report_styling'],
          'drupalSettings' => [
            'accessibility' => ['aiEnabled' => $use_ai],
          ],
        ],
      ];
    }
    catch (RequestException $e) {
      $this->messenger()->addError($this->t('Failed to retrieve the page for scanning: @error', ['@error' => $e->getMessage()]));
      $this->getLogger('accessibility')->error('Unable to retrieve page for generateReport: @error', ['@error' => $e->getMessage()]);
      return [];
    }
  }

}
