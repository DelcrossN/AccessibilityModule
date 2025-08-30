<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\AccessibilityStatsController.
 *
 * Controller for accessibility statistics and chart data management.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\accessibility\Service\AccessibilityCacheService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the accessibility statistics page.
 */
class AccessibilityStatsController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The accessibility cache service.
   *
   * @var \Drupal\accessibility\Service\AccessibilityCacheService
   */
  protected $cacheService;

  /**
   * Constructs a new AccessibilityStatsController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\accessibility\Service\AccessibilityCacheService $cache_service
   *   The accessibility cache service.
   */
  public function __construct(Connection $database, AccessibilityCacheService $cache_service) {
    $this->database = $database;
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('accessibility.cache_service')
    );
  }

  /**
   * Build the statistics page.
   *
   * @return array
   *   A render array for the statistics page.
   */
  public function build() {
    // Get aggregated stats from cache service
    $stats = $this->cacheService->getAggregatedStats();
    
    // Get daily scan counts for chart
    $daily_scan_data = $this->cacheService->getDailyScanCounts();
    
    return [
      '#theme' => 'accessibility_stats',
      '#attached' => [
        'library' => ['accessibility/stats'],
        'drupalSettings' => [
          'accessibility' => [
            'statsUrl' => Url::fromRoute('accessibility.cache.get_stats')->toString(),
            'chartData' => $daily_scan_data,
            'stats' => $stats,
          ],
        ],
      ],
      '#dashboard_url' => Url::fromRoute('accessibility.dashboard')->toString(),
      '#config_url' => Url::fromRoute('accessibility.settings')->toString(),
      '#unique_pages' => $stats['unique_urls_scanned'] ?? 0,
      '#total_violations' => $stats['total_violations'] ?? 0,
      '#violation_stats' => [
        'critical' => $stats['critical_violations'] ?? 0,
        'serious' => $stats['serious_violations'] ?? 0,
        'moderate' => $stats['moderate_violations'] ?? 0,
        'minor' => $stats['minor_violations'] ?? 0,
      ],
    ];
  }

  /**
   * AJAX callback to fetch updated statistics data.
   */
  public function handleAjax(Request $request) {
    $data = $this->getStatsData($request);
    return new JsonResponse($data);
  }

  /**
   * Temporary method to populate test data for chart testing.
   * This can be accessed at /admin/reports/accessibility/populate-test-data
   */
  public function populateTestData() {
    // Generate test data for the last 7 days
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    $test_urls = [
      $base_url . '/',
      $base_url . '/accessibility/test-violations',
      $base_url . '/node/1',
    ];

    $violations_templates = [
      [
        'impact' => 'critical',
        'description' => 'Images must have alternate text',
        'help_url' => 'https://dequeuniversity.com/rules/axe/4.4/image-alt',
        'nodes' => serialize([['target' => ['.hero-image'], 'html' => '<img src="/hero.jpg">']])
      ],
      [
        'impact' => 'serious',
        'description' => 'Elements must have sufficient color contrast',
        'help_url' => 'https://dequeuniversity.com/rules/axe/4.4/color-contrast',
        'nodes' => serialize([['target' => ['#main-nav'], 'html' => '<a class="nav-link">About</a>']])
      ],
      [
        'impact' => 'moderate',
        'description' => 'Page must have a main landmark',
        'help_url' => 'https://dequeuniversity.com/rules/axe/4.4/landmark-one-main',
        'nodes' => serialize([['target' => ['body'], 'html' => '<body>...</body>']])
      ],
    ];

    $records_added = 0;

    // Generate data for last 7 days
    for ($i = 6; $i >= 0; $i--) {
      // Vary the number of scans per day (0-3 scans)
      $scans_per_day = rand(0, 3);
      
      for ($scan = 0; $scan < $scans_per_day; $scan++) {
        // Create unique timestamp for each scan (different times of day)
        $timestamp = strtotime("-$i days") + rand(3600 * 8, 3600 * 20); // Random time between 8AM-8PM
        
        // Pick a random URL for this scan
        $url = $test_urls[array_rand($test_urls)];
        
        // Add 2-3 violations per scan (all with same timestamp)
        $violations_count = rand(2, 3);
        for ($v = 0; $v < $violations_count; $v++) {
          $violation = $violations_templates[array_rand($violations_templates)];
          
          $this->database->insert('accessibility_violations')
            ->fields([
              'scanned_url' => $url,
              'impact' => $violation['impact'],
              'description' => $violation['description'],
              'help_url' => $violation['help_url'],
              'nodes' => $violation['nodes'],
              'timestamp' => $timestamp,
            ])
            ->execute();
          
          $records_added++;
        }
      }
    }

    return [
      '#markup' => "Successfully populated $records_added test records for the last 7 days. <br><a href=\"" . 
                   Url::fromRoute('accessibility.stats')->toString() . 
                   "\">View Stats Page</a>",
    ];
  }

  /**
   * Debug method to check current database content and chart data.
   */
  public function debugData() {
    $output = [];
    
    // Check what's in the database
    $query = $this->database->select('accessibility_violations', 'av');
    $query->addExpression("FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:%i:%s')", 'formatted_timestamp');
    $query->addExpression("FROM_UNIXTIME(timestamp, '%Y-%m-%d')", 'scan_date');
    $query->fields('av', ['scanned_url', 'timestamp']);
    $query->orderBy('timestamp', 'DESC');
    $query->range(0, 20); // Limit to last 20 records
    $results = $query->execute()->fetchAll();
    
    $output[] = '<h3>Last 20 Database Records:</h3>';
    if (empty($results)) {
      $output[] = '<p>No records found in accessibility_violations table.</p>';
    } else {
      $output[] = '<table border="1"><tr><th>URL</th><th>Timestamp</th><th>Formatted Date</th><th>Scan Date</th></tr>';
      foreach ($results as $record) {
        $output[] = '<tr><td>' . $record->scanned_url . '</td><td>' . $record->timestamp . '</td><td>' . $record->formatted_timestamp . '</td><td>' . $record->scan_date . '</td></tr>';
      }
      $output[] = '</table>';
    }
    
    // Test our cache service method
    $output[] = '<h3>Cache Service Chart Data:</h3>';
    $chart_data = $this->cacheService->getDailyScanCounts();
    $output[] = '<pre>' . print_r($chart_data, true) . '</pre>';
    
    // Test our stats controller method for last 7 days
    $output[] = '<h3>Real Scan Counts Query Test:</h3>';
    $seven_days_ago = strtotime('-7 days');
    $today = strtotime('today 23:59:59');
    
    $query = $this->database->select('accessibility_violations', 'av');
    $query->addExpression("FROM_UNIXTIME(timestamp, '%Y-%m-%d')", 'scan_date');
    $query->addExpression('COUNT(DISTINCT timestamp)', 'scan_count');
    $query->condition('timestamp', [$seven_days_ago, $today], 'BETWEEN');
    $query->groupBy('scan_date');
    $results = $query->execute()->fetchAllKeyed();
    
    $output[] = '<p>Seven days ago timestamp: ' . $seven_days_ago . ' (' . date('Y-m-d H:i:s', $seven_days_ago) . ')</p>';
    $output[] = '<p>Today timestamp: ' . $today . ' (' . date('Y-m-d H:i:s', $today) . ')</p>';
    $output[] = '<pre>' . print_r($results, true) . '</pre>';
    
    return [
      '#markup' => implode("\n", $output),
    ];
  }

  /**
   * Gathers all statistics data by orchestrating helper methods.
   */
  private function getStatsData(Request $request): array {
    $date_params = $this->getDateRangeParameters($request);

    if (empty($date_params)) {
      return ['summary' => [], 'chart' => ['data' => []], 'top_issues' => []];
    }

    $start_time = $date_params['start_dt']->getTimestamp();
    $end_time = $date_params['end_dt']->getTimestamp();

    return [
      'summary' => $this->getSummaryData($start_time, $end_time),
      'chart' => ['data' => $this->getChartData($date_params)],
      'top_issues' => $this->getTopIssues($start_time, $end_time),
    ];
  }

  /**
   * Calculates the date range and interval based on request parameters.
   */
  private function getDateRangeParameters(Request $request): ?array {
    $timeframe_param = $request->query->get('timeframe', 30);

    if ($timeframe_param === 'custom') {
      $view_by = $request->query->get('view_by');

      if ($view_by === 'day') {
        $date_str = $request->query->get('date');
        if (empty($date_str)) {
          return NULL;
        }
        $selected_date = new \DateTime($date_str);
        $start_dt = (clone $selected_date)->setTime(0, 0, 0);
        $end_dt = (clone $selected_date)->setTime(23, 59, 59);
        return [
          'start_dt' => $start_dt,
          'end_dt' => $end_dt,
          'interval_spec' => 'PT1H',
          'sql_group_format' => '%Y-%m-%d %H',
        ];
      }
      elseif ($view_by === 'week') {
        $start_str = $request->query->get('start_date');
        $end_str = $request->query->get('end_date');
        if (empty($start_str) || empty($end_str)) {
          return NULL;
        }
        $start_dt = (new \DateTime($start_str))->setTime(0, 0, 0);
        $end_dt = (new \DateTime($end_str))->setTime(23, 59, 59);
        return [
          'start_dt' => $start_dt,
          'end_dt' => $end_dt,
          'interval_spec' => 'P1D',
          'sql_group_format' => '%Y-%m-%d',
        ];
      }
      // If view_by is not 'day' or 'week', it's an incomplete request.
      return NULL;
    }
    else {
      // This is the missing block for standard timeframes.
      $timeframe_days = (int) $timeframe_param;
      $end_dt = (new \DateTime())->setTime(23, 59, 59);
      $start_dt = (new \DateTime())->setTimestamp(time() - ($timeframe_days * 86400))->setTime(0, 0, 0);
      return [
        'start_dt' => $start_dt,
        'end_dt' => $end_dt,
        'interval_spec' => 'P1D',
        'sql_group_format' => '%Y-%m-%d',
      ];
    }
  } // <-- This was the missing closing brace.

  /**
   * Queries and formats the data specifically for the time-series chart.
   */
  private function getChartData(array $date_params): array {
    $query = $this->database->select('accessibility_violations', 'av');
    $query->condition('av.timestamp', [$date_params['start_dt']->getTimestamp(), $date_params['end_dt']->getTimestamp()], 'BETWEEN');
    $query->addExpression("FROM_UNIXTIME(av.timestamp, '{$date_params['sql_group_format']}')", 'scan_unit');
    $query->addExpression('COUNT(av.id)', 'issue_count');
    $query->groupBy('scan_unit');
    $query->orderBy('scan_unit');
    $results = $query->execute()->fetchAllKeyed();

    $chart_data = [];
    $period = new \DatePeriod($date_params['start_dt'], new \DateInterval($date_params['interval_spec']), $date_params['end_dt']);
    $php_date_format = str_replace(['%Y', '%m', '%d', '%H'], ['Y', 'm', 'd', 'H'], $date_params['sql_group_format']);

    foreach ($period as $date) {
      $date_string = $date->format($php_date_format);
      $timestamp_ms = $date->getTimestamp() * 1000;
      $chart_data[] = [
        'x' => $timestamp_ms,
        'y' => $results[$date_string] ?? 0,
      ];
    }
    return $chart_data;
  }

  /**
   * Queries the data for the summary cards.
   */
  private function getSummaryData(int $start_time, int $end_time): array {
    $query_total = $this->database->select('accessibility_violations', 'av');
    $query_total->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $total_violations = (int) $query_total->countQuery()->execute()->fetchField();

    // Get violation counts by impact level
    $query_critical = $this->database->select('accessibility_violations', 'av');
    $query_critical->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query_critical->condition('av.impact', 'critical');
    $critical_violations = (int) $query_critical->countQuery()->execute()->fetchField();

    $query_serious = $this->database->select('accessibility_violations', 'av');
    $query_serious->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query_serious->condition('av.impact', 'serious');
    $serious_violations = (int) $query_serious->countQuery()->execute()->fetchField();

    $query_moderate = $this->database->select('accessibility_violations', 'av');
    $query_moderate->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query_moderate->condition('av.impact', 'moderate');
    $moderate_violations = (int) $query_moderate->countQuery()->execute()->fetchField();

    $query_minor = $this->database->select('accessibility_violations', 'av');
    $query_minor->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query_minor->condition('av.impact', 'minor');
    $minor_violations = (int) $query_minor->countQuery()->execute()->fetchField();

    $query_pages = $this->database->select('accessibility_violations', 'av');
    $query_pages->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $unique_pages = (int) $query_pages->distinct()->fields('av', ['scanned_url'])->countQuery()->execute()->fetchField();

    return [
      'unique_pages' => $unique_pages,
      'total_violations' => $total_violations,
      'critical_violations' => $critical_violations,
      'serious_violations' => $serious_violations,
      'moderate_violations' => $moderate_violations,
      'minor_violations' => $minor_violations,
    ];
  }

  /**
   * Queries the data for the "Top Issues" list.
   */
  private function getTopIssues(int $start_time, int $end_time): array {
    $query = $this->database->select('accessibility_violations', 'av');
    $query->fields('av', ['description']);
    $query->addExpression('COUNT(av.id)', 'issue_count');
    $query->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query->groupBy('av.description');
    $query->orderBy('issue_count', 'DESC');
    $query->range(0, 5);
    return $query->execute()->fetchAll();
  }

}
