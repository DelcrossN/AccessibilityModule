<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\AxeReportController.
 *
 * Controller for handling axe accessibility scan reports.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\accessibility\Service\AccessibilityCacheService;

/**
 * Controller for handling Axe accessibility scan reports.
 */
class AxeReportController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The accessibility cache service.
   *
   * @var \Drupal\accessibility\Service\AccessibilityCacheService
   */
  protected $cacheService;

  /**
   * Constructs a new AxeReportController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\accessibility\Service\AccessibilityCacheService $cache_service
   *   The accessibility cache service.
   */
  public function __construct(Connection $database, LoggerInterface $logger, AccessibilityCacheService $cache_service) {
    $this->database = $database;
    $this->logger = $logger;
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('logger.factory')->get('accessibility'),
      $container->get('accessibility.cache_service')
    );
  }

  /**
   * Display the test violations page.
   */
  public function testViolations() {
    return [
      '#theme' => 'accessibility_test_violations',
      '#attached' => [
        'library' => [
          'accessibility/accessibility_scanner',
        ],
      ],
    ];
  }

  /**
   * Save accessibility scan report from axe-core.
   *
   * Accepts POST data with the scan results and saves violations to the database
   * and caches the results using the AccessibilityCacheService.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing scan data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function saveReport(Request $request) {
    try {
      // Get the JSON data from the request
      $data = json_decode($request->getContent(), TRUE);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('Invalid JSON data received');
      }

      // Validate required fields
      if (empty($data['url']) || !isset($data['violations'])) {
        throw new \InvalidArgumentException('Missing required fields: url and violations');
      }

      $url = $data['url'];
      $violations = $data['violations'];
      $timestamp = time();

      // First, delete any existing violations for this URL to avoid duplicates
      $this->database->delete('accessibility_violations')
        ->condition('scanned_url', $url)
        ->execute();

      // Insert new violations into database (for legacy compatibility)
      if (!empty($violations)) {
        $insert_query = $this->database->insert('accessibility_violations')
          ->fields([
            'scanned_url',
            'impact',
            'description', 
            'help_url',
            'nodes',
            'timestamp',
          ]);

        foreach ($violations as $violation) {
          // Extract violation data with fallbacks
          $impact = $violation['impact'] ?? 'minor';
          $description = $violation['description'] ?? $violation['help'] ?? 'Unknown issue';
          $help_url = $violation['helpUrl'] ?? '';
          
          // Serialize node data
          $nodes_data = serialize($violation['nodes'] ?? []);

          // Truncate description if too long for varchar(255)
          if (strlen($description) > 255) {
            $description = substr($description, 0, 252) . '...';
          }

          $insert_query->values([
            'scanned_url' => $url,
            'impact' => $impact,
            'description' => $description,
            'help_url' => $help_url,
            'nodes' => $nodes_data,
            'timestamp' => $timestamp,
          ]);
        }

        $insert_query->execute();
      }

      // *** CRITICAL: Cache the scan results using AccessibilityCacheService ***
      // This is what the reports page reads from
      $scan_data = [
        'violations' => $violations,
        'url' => $url,
        'timestamp' => $timestamp,
        'scan_timestamp' => $timestamp,
      ];
      
      // Cache the scan results - this will update aggregated stats automatically
      $this->cacheService->cacheScanResults($url, $scan_data);

      $this->logger->info('Saved and cached accessibility scan report for @url with @count violations', [
        '@url' => $url,
        '@count' => count($violations),
      ]);

      // Get updated aggregated stats to return in response
      $aggregated_stats = $this->cacheService->getAggregatedStats();

      return new JsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Report saved and cached successfully',
        'violations_count' => count($violations),
        'url' => $url,
        'summary' => [
          'total_violations' => $aggregated_stats['total_violations'] ?? 0,
          'unique_pages_scanned' => $aggregated_stats['unique_urls_scanned'] ?? 0,
          'critical_violations' => $aggregated_stats['critical_violations'] ?? 0,
          'serious_violations' => $aggregated_stats['serious_violations'] ?? 0,
          'moderate_violations' => $aggregated_stats['moderate_violations'] ?? 0,
          'minor_violations' => $aggregated_stats['minor_violations'] ?? 0,
        ],
      ]);

    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save accessibility report: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to save report: ' . $e->getMessage(),
        'error' => $e->getMessage(),
      ], 500);
    }
  }

}
