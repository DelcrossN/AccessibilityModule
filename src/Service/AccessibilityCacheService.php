<?php

/**
 * @file
 * Contains \Drupal\accessibility\Service\AccessibilityCacheService.
 *
 * Service for caching and managing accessibility scan data.
 */

namespace Drupal\accessibility\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;

/**
 * Service for caching accessibility scan data.
 */
class AccessibilityCacheService {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Cache tags for accessibility data.
   */
  const CACHE_TAGS = ['accessibility_scan_data'];

  /**
   * Cache bin for accessibility data.
   */
  const CACHE_BIN = 'accessibility_scan_cache';

  /**
   * Constructs a new AccessibilityCacheService.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(CacheBackendInterface $cache, Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->cache = $cache;
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Cache scan results for a specific URL.
   *
   * @param string $url
   *   The URL that was scanned.
   * @param array $scan_data
   *   The scan results data containing violations.
   */
  public function cacheScanResults($url, array $scan_data) {
    try {
      // Normalize the URL
      $normalized_url = $this->normalizeUrl($url);
      
      // Track daily scan count
      $this->trackDailyScanCount();
      
      // Prepare the data structure
      $cache_data = [
        'url' => $normalized_url,
        'scan_timestamp' => time(),
        'violations' => [],
        'violation_counts' => [
          'total' => 0,
          'critical' => 0,
          'serious' => 0,
          'moderate' => 0,
          'minor' => 0,
        ],
      ];

      // Process violations if they exist
      if (isset($scan_data['violations']) && is_array($scan_data['violations'])) {
        foreach ($scan_data['violations'] as $violation) {
          $violation_data = [
            'id' => $violation['id'] ?? '',
            'impact' => $violation['impact'] ?? 'unknown',
            'description' => $violation['description'] ?? '',
            'help' => $violation['help'] ?? '',
            'helpUrl' => $violation['helpUrl'] ?? '',
            'tags' => $violation['tags'] ?? [],
            'nodes' => isset($violation['nodes']) ? count($violation['nodes']) : 0,
          ];
          
          $cache_data['violations'][] = $violation_data;
          $cache_data['violation_counts']['total']++;
          
          // Count by impact level
          switch ($violation_data['impact']) {
            case 'critical':
              $cache_data['violation_counts']['critical']++;
              break;
            case 'serious':
              $cache_data['violation_counts']['serious']++;
              break;
            case 'moderate':
              $cache_data['violation_counts']['moderate']++;
              break;
            case 'minor':
              $cache_data['violation_counts']['minor']++;
              break;
          }
        }
      }

      // Cache the URL-specific data
      $cache_id = 'accessibility_scan_url:' . md5($normalized_url);
      $this->cache->set($cache_id, $cache_data, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);

      // Update the URLs list cache
      $this->updateUrlsList($normalized_url);
      
      // Update aggregated statistics immediately after caching new scan results
      $this->updateAggregatedStats();

      $this->loggerFactory->get('accessibility')->info('Cached scan results for URL: @url with @count violations', [
        '@url' => $normalized_url,
        '@count' => $cache_data['violation_counts']['total']
      ]);
      
    } catch (\Exception $e) {
      $this->loggerFactory->get('accessibility')->error('Error caching scan results: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Get cached scan results for a specific URL.
   *
   * @param string $url
   *   The URL to get results for.
   *
   * @return array|null
   *   The cached scan data or null if not found.
   */
  public function getScanResults($url) {
    $normalized_url = $this->normalizeUrl($url);
    $cache_id = 'accessibility_scan_url:' . md5($normalized_url);
    
    $cache_item = $this->cache->get($cache_id);
    return $cache_item ? $cache_item->data : NULL;
  }

  /**
   * Get all URLs that have been scanned.
   *
   * @return array
   *   Array of URLs with scan button.
   */
  public function getScannedUrls() {
    $cache_item = $this->cache->get('accessibility_scanned_urls');
    return $cache_item ? $cache_item->data : [];
  }

  /**
   * Get aggregated statistics for all scanned URLs.
   *
   * @return array
   *   Aggregated statistics.
   */
  public function getAggregatedStats() {
    $cache_item = $this->cache->get('accessibility_aggregated_stats');
    
    if ($cache_item) {
      return $cache_item->data;
    }
    
    // If no cached stats, calculate them
    return $this->calculateAggregatedStats();
  }

  /**
   * Get detailed statistics for all scanned URLs.
   *
   * @return array
   *   Detailed statistics organized by URL.
   */
  public function getDetailedStats() {
    $scanned_urls = $this->getScannedUrls();
    $detailed_stats = [];
    
    foreach ($scanned_urls as $url) {
      $scan_results = $this->getScanResults($url);
      if ($scan_results) {
        $detailed_stats[$url] = [
          'url' => $url,
          'last_scanned' => $scan_results['scan_timestamp'],
          'violation_counts' => $scan_results['violation_counts'],
          'violations' => $scan_results['violations'],
        ];
      }
    }
    
    return $detailed_stats;
  }

  /**
   * Clear all accessibility scan cache data.
   */
  public function clearAllCache() {
    $this->cache->invalidateTags(self::CACHE_TAGS);
    $this->loggerFactory->get('accessibility')->info('Cleared all accessibility scan cache data');
  }

  /**
   * Clear cache for a specific URL.
   *
   * @param string $url
   *   The URL to clear cache for.
   */
  public function clearUrlCache($url) {
    $normalized_url = $this->normalizeUrl($url);
    $cache_id = 'accessibility_scan_url:' . md5($normalized_url);
    $this->cache->delete($cache_id);
    
    // Remove from URLs list
    $this->removeFromUrlsList($normalized_url);
    
    // Update aggregated stats
    $this->updateAggregatedStats();
    
    $this->loggerFactory->get('accessibility')->info('Cleared cache for URL: @url', ['@url' => $normalized_url]);
  }

  /**
   * Update the list of scanned URLs.
   *
   * @param string $url
   *   The URL to add to the list.
   */
  protected function updateUrlsList($url) {
    $cache_item = $this->cache->get('accessibility_scanned_urls');
    $urls = $cache_item ? $cache_item->data : [];
    
    if (!in_array($url, $urls)) {
      $urls[] = $url;
      $this->cache->set('accessibility_scanned_urls', $urls, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);
    }
  }

  /**
   * Remove URL from the scanned URLs list.
   *
   * @param string $url
   *   The URL to remove.
   */
  protected function removeFromUrlsList($url) {
    $cache_item = $this->cache->get('accessibility_scanned_urls');
    $urls = $cache_item ? $cache_item->data : [];
    
    $key = array_search($url, $urls);
    if ($key !== FALSE) {
      unset($urls[$key]);
      $urls = array_values($urls); // Re-index array
      $this->cache->set('accessibility_scanned_urls', $urls, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);
    }
  }

  /**
   * Update aggregated statistics.
   */
  protected function updateAggregatedStats() {
    $stats = $this->calculateAggregatedStats();
    $this->cache->set('accessibility_aggregated_stats', $stats, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);
  }

  /**
   * Calculate aggregated statistics from all cached scan results.
   *
   * @return array
   *   Aggregated statistics.
   */
  protected function calculateAggregatedStats() {
    $scanned_urls = $this->getScannedUrls();
    
    $aggregated_stats = [
      'unique_urls_scanned' => count($scanned_urls),
      'total_violations' => 0,
      'critical_violations' => 0,
      'serious_violations' => 0,
      'moderate_violations' => 0,
      'minor_violations' => 0,
      'by_url' => [],
    ];
    
    foreach ($scanned_urls as $url) {
      $scan_results = $this->getScanResults($url);
      if ($scan_results && isset($scan_results['violation_counts'])) {
        $counts = $scan_results['violation_counts'];
        
        // Add to totals
        $aggregated_stats['total_violations'] += $counts['total'];
        $aggregated_stats['critical_violations'] += $counts['critical'];
        $aggregated_stats['serious_violations'] += $counts['serious'];
        $aggregated_stats['moderate_violations'] += $counts['moderate'];
        $aggregated_stats['minor_violations'] += $counts['minor'];
        
        // Store by URL
        $aggregated_stats['by_url'][$url] = $counts;
      }
    }
    
    return $aggregated_stats;
  }

  /**
   * Normalize URL for consistent caching.
   *
   * @param string $url
   *   The URL to normalize.
   *
   * @return string
   *   The normalized URL.
   */
  protected function normalizeUrl($url) {
    // Remove trailing slashes and normalize
    $normalized = rtrim($url, '/');
    
    // Ensure it starts with http:// or https://
    if (!preg_match('/^https?:\/\//', $normalized)) {
      $normalized = 'https://' . ltrim($normalized, '/');
    }
    
    return $normalized;
  }

  /**
   * Record that a URL has the scan button (for tracking purposes).
   *
   * @param string $url
   *   The URL that has the scan button.
   */
  public function recordScanButtonUrl($url) {
    $normalized_url = $this->normalizeUrl($url);
    
    // Get existing button URLs
    $cache_item = $this->cache->get('accessibility_scan_button_urls');
    $button_urls = $cache_item ? $cache_item->data : [];
    
    if (!in_array($normalized_url, $button_urls)) {
      $button_urls[] = $normalized_url;
      $this->cache->set('accessibility_scan_button_urls', $button_urls, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);
    }
  }

  /**
   * Get all URLs that have the scan button.
   *
   * @return array
   *   Array of URLs with scan buttons.
   */
  public function getScanButtonUrls() {
    $cache_item = $this->cache->get('accessibility_scan_button_urls');
    return $cache_item ? $cache_item->data : [];
  }

  /**
   * Track daily scan count for chart data.
   */
  protected function trackDailyScanCount() {
    $today = date('Y-m-d');
    $cache_item = $this->cache->get('accessibility_daily_scan_counts');
    $daily_counts = $cache_item ? $cache_item->data : [];
    
    if (!isset($daily_counts[$today])) {
      $daily_counts[$today] = 0;
    }
    
    $daily_counts[$today]++;
    
    // Keep only last 30 days to prevent unlimited growth
    $cutoff_date = date('Y-m-d', strtotime('-30 days'));
    foreach ($daily_counts as $date => $count) {
      if ($date < $cutoff_date) {
        unset($daily_counts[$date]);
      }
    }
    
    $this->cache->set('accessibility_daily_scan_counts', $daily_counts, CacheBackendInterface::CACHE_PERMANENT, self::CACHE_TAGS);
  }

  /**
   * Get real scan counts from database for the last 7 days.
   *
   * @return array
   *   Array of scan counts by date (Y-m-d format).
   */
  private function getRealScanCounts() {
    $seven_days_ago = strtotime('-7 days');
    $today = strtotime('today 23:59:59');
    
    // Query to get unique scan dates (distinct dates when scans occurred)
    // We count distinct timestamps per day to get the number of scans per day
    $query = $this->database->select('accessibility_violations', 'av');
    $query->addExpression("FROM_UNIXTIME(timestamp, '%Y-%m-%d')", 'scan_date');
    $query->addExpression('COUNT(DISTINCT timestamp)', 'scan_count');
    $query->condition('timestamp', [$seven_days_ago, $today], 'BETWEEN');
    $query->groupBy('scan_date');
    $results = $query->execute()->fetchAllKeyed();
    
    return $results;
  }

  /**
   * Get daily scan counts for the last 7 days.
   *
   * @return array
   *   Array of dates and scan counts for chart display.
   */
  public function getDailyScanCounts() {
    $chart_data = [];
    
    // Get real scan data from database for last 7 days
    $daily_counts = $this->getRealScanCounts();
    
    // Generate last 7 days
    for ($i = 6; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $display_date = date('M j', strtotime($date)); // Format: Aug 29
      $count = isset($daily_counts[$date]) ? $daily_counts[$date] : 0;
      
      $chart_data[] = [
        'date' => $display_date,
        'count' => $count,
      ];
    }
    
    return $chart_data;
  }

}