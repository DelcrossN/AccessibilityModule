<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
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
   * Constructs a new AccessibilityStatsController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Builds the initial accessibility statistics page shell.
   */
  public function build() {
    return [
      '#theme' => 'accessibility_stats',
      '#dashboard_url' => Url::fromRoute('accessibility.dashboard')->toString(),
      '#config_url' => Url::fromRoute('accessibility.settings')->toString(),
      '#attached' => [
        'library' => [
          'accessibility/stats',
        ],
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
    $total_issues = (int) $query_total->countQuery()->execute()->fetchField();

    $query_high = $this->database->select('accessibility_violations', 'av');
    $query_high->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $query_high->condition('av.impact', ['critical', 'serious'], 'IN');
    $high_priority = (int) $query_high->countQuery()->execute()->fetchField();

    $query_pages = $this->database->select('accessibility_violations', 'av');
    $query_pages->condition('av.timestamp', [$start_time, $end_time], 'BETWEEN');
    $pages_scanned = (int) $query_pages->distinct()->fields('av', ['scanned_url'])->countQuery()->execute()->fetchField();

    return [
      'total_issues' => $total_issues,
      'fixed_issues' => 0, // Placeholder, requires more complex logic.
      'high_priority' => $high_priority,
      'pages_scanned' => $pages_scanned,
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
