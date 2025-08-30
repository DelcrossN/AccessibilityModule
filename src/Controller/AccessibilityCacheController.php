<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\AccessibilityCacheController.
 *
 * Controller for managing accessibility scan result caching.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\accessibility\Service\AccessibilityCacheService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Controller for handling accessibility scan caching requests.
 */
class AccessibilityCacheController extends ControllerBase {

  /**
   * The accessibility cache service.
   *
   * @var \Drupal\accessibility\Service\AccessibilityCacheService
   */
  protected $cacheService;

  /**
   * Constructs a new AccessibilityCacheController.
   *
   * @param \Drupal\accessibility\Service\AccessibilityCacheService $cache_service
   *   The accessibility cache service.
   */
  public function __construct(AccessibilityCacheService $cache_service) {
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('accessibility.cache_service')
    );
  }

  /**
   * Cache scan results from AJAX request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function cacheScanResults(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      
      if (!isset($data['url']) || !isset($data['scan_results'])) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Missing required parameters: url and scan_results',
        ], 400);
      }

      $url = $data['url'];
      $scan_results = $data['scan_results'];
      
      // Cache the scan results
      $this->cacheService->cacheScanResults($url, $scan_results);
      
      // Also record that this URL has a scan button
      $this->cacheService->recordScanButtonUrl($url);
      
      // Get the cached data to return accurate counts
      $cached_data = $this->cacheService->getScanResults($url);
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Scan results cached successfully',
        'cached_data' => $cached_data,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->error('Error caching scan results: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error caching scan results: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Record that a URL has a scan button.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function recordScanButton(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      
      if (!isset($data['url'])) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Missing required parameter: url',
        ], 400);
      }

      $url = $data['url'];
      
      // Record that this URL has a scan button
      $this->cacheService->recordScanButtonUrl($url);
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Scan button URL recorded successfully',
        'url' => $url,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->error('Error recording scan button URL: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error recording scan button URL: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get cached scan results for a URL.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with cached scan results.
   */
  public function getCachedResults(Request $request) {
    try {
      $url = $request->query->get('url');
      
      if (!$url) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Missing required parameter: url',
        ], 400);
      }

      $cached_results = $this->cacheService->getScanResults($url);
      
      return new JsonResponse([
        'success' => TRUE,
        'data' => $cached_results,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->error('Error getting cached results: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error getting cached results: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get aggregated statistics.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with aggregated statistics.
   */
  public function getAggregatedStats() {
    try {
      $stats = $this->cacheService->getAggregatedStats();
      $detailed_stats = $this->cacheService->getDetailedStats();
      $scan_button_urls = $this->cacheService->getScanButtonUrls();
      
      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'aggregated_stats' => $stats,
          'detailed_stats' => $detailed_stats,
          'scan_button_urls' => $scan_button_urls,
        ],
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->error('Error getting aggregated stats: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error getting aggregated stats: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Clear cache for specific URL or all cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function clearCache(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      
      if (isset($data['url'])) {
        // Clear cache for specific URL
        $this->cacheService->clearUrlCache($data['url']);
        $message = 'Cache cleared for URL: ' . $data['url'];
      } else {
        // Clear all cache
        $this->cacheService->clearAllCache();
        $message = 'All accessibility scan cache cleared';
      }
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => $message,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->error('Error clearing cache: @error', ['@error' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error clearing cache: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Access check for cache operations.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Allow users with 'use accessibility tools' permission
    return AccessResult::allowedIfHasPermission($account, 'use accessibility tools');
  }

}
