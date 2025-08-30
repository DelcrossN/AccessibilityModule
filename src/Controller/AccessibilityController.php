<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\AccessibilityController.
 *
 * Main controller for accessibility dashboard and reporting functionality.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\accessibility\Service\AccessibilityApiClient;
use Drupal\accessibility\Service\AccessibilityCacheService;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Database\Connection;

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
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

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
   * Constructs a new AccessibilityController.
   *
   * @param \Drupal\accessibility\Service\AccessibilityApiClient $api_client
   *   The accessibility API client.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\accessibility\Service\AccessibilityCacheService $cache_service
   *   The accessibility cache service.
   */
  public function __construct(AccessibilityApiClient $api_client, ClientInterface $http_client, Connection $database, AccessibilityCacheService $cache_service) {
    $this->apiClient = $api_client;
    $this->httpClient = $http_client;
    $this->database = $database;
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('accessibility.api_client'),
      $container->get('http_client'),
      $container->get('database'),
      $container->get('accessibility.cache_service')
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
      '#test_violations_url' => Url::fromRoute('accessibility.test_violations')->toString(),
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
    $original_path = $path;
    $path = '/' . ltrim($path, '/');
    
    // Convert slug back to friendly title
    $friendly_title = $this->convertSlugToTitle($path);
    $build['#title'] = $friendly_title;

    // Try to find cached data that matches the current report page URL
    $scanned_urls = $this->cacheService->getScannedUrls();
    
    // Convert slug back to possible URLs to match against cache
    $possible_urls = $this->generatePossibleUrlsFromSlug($original_path);
    
    $cached_data = null;
    // Find exact URL matches with detailed logging for debugging
    foreach ($possible_urls as $target_url) {
      foreach ($scanned_urls as $cached_url) {
        if ($this->urlsMatch($target_url, $cached_url)) {
          $scan_data = $this->cacheService->getScanResults($cached_url);
          if ($scan_data) {
            $this->getLogger('accessibility')->info('Found exact match for report: @target matched @cached', [
              '@target' => $target_url,
              '@cached' => $cached_url
            ]);
            $cached_data = $scan_data;
            break 2;
          }
        }
      }
    }
    
    // Log if no match found for debugging
    if (!$cached_data) {
      $this->getLogger('accessibility')->info('No URL match found for path @path. Available URLs: @urls', [
        '@path' => $original_path,
        '@urls' => implode(', ', $scanned_urls)
      ]);
    }
    
    // If no specific match found, try fallback but only for the same page type
    if (!$cached_data) {
      // For violations test page, allow fallback to any violations data
      if (strpos($original_path, 'accessibility') !== false && strpos($original_path, 'test') !== false) {
        foreach ($scanned_urls as $url) {
          if (strpos($url, 'accessibility') !== false && strpos($url, 'test') !== false) {
            $scan_data = $this->cacheService->getScanResults($url);
            if ($scan_data && !empty($scan_data['violations'])) {
              $cached_data = $scan_data;
              $this->getLogger('accessibility')->info('Using fallback violations data from: @url', ['@url' => $url]);
              break;
            }
          }
        }
      }
      
      // Final fallback: empty report
      if (!$cached_data) {
        $this->getLogger('accessibility')->warning('No violation data found for path: @path', ['@path' => $original_path]);
        $cached_data = [
          'violations' => [],
          'violation_counts' => [
            'total' => 0,
            'critical' => 0,
            'serious' => 0,
            'moderate' => 0,
            'minor' => 0,
          ],
          'url' => $possible_urls[0] ?? '',
          'scan_timestamp' => null,
        ];
      }
    }
    
    // Initialize violation counts - JavaScript will update these in real-time
    $violation_counts = [
      'total' => 0,
      'critical' => 0,
      'serious' => 0,
      'moderate' => 0,
      'minor' => 0,
    ];

    // Always show violations if we have cached data, regardless of path matching
    $build['report'] = [
      '#theme' => 'accessibility_report',
      '#cached_violations' => $cached_data ? ($cached_data['violations'] ?? []) : [],
      '#violation_counts' => $violation_counts,
      '#scanned_url' => $cached_data ? ($cached_data['url'] ?? null) : null,
      '#scan_timestamp' => $cached_data ? ($cached_data['scan_timestamp'] ?? null) : null,
      '#page_title' => $friendly_title,
      '#attached' => [
        'library' => ['accessibility/comprehensive_report', 'accessibility/report_violations'],
        'drupalSettings' => [
          'accessibilityReport' => [
            'violations' => $cached_data ? ($cached_data['violations'] ?? []) : [],
            'violationCounts' => $violation_counts,
          ],
        ],
      ],
      '#cache' => ['contexts' => ['url.path'], 'tags' => ['accessibility:report', 'accessibility_scan_data']],
    ];

    return $build;
  }

  /**
   * Generate possible URLs from a slug to match against cached data.
   */
  private function generatePossibleUrlsFromSlug($slug) {
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    
    // Handle root/home cases
    if ($slug === '/' || $slug === 'home' || $slug === 'main-page' || empty($slug)) {
      return [
        $base_url,
        $base_url . '/',
      ];
    }
    
    // Generate systematic URL variations for any slug
    $possible_urls = [];
    
    // Direct slug mapping
    $possible_urls[] = $base_url . '/' . $slug;
    $possible_urls[] = $base_url . '/' . $slug . '/';
    
    // Convert hyphens to slashes (for nested paths)
    if (strpos($slug, '-') !== false) {
      $path_with_slashes = str_replace('-', '/', $slug);
      $possible_urls[] = $base_url . '/' . $path_with_slashes;
      $possible_urls[] = $base_url . '/' . $path_with_slashes . '/';
    }
    
    // Convert hyphens to underscores (common in URLs)
    if (strpos($slug, '-') !== false) {
      $path_with_underscores = str_replace('-', '_', $slug);
      $possible_urls[] = $base_url . '/' . $path_with_underscores;
      $possible_urls[] = $base_url . '/' . $path_with_underscores . '/';
    }
    
    return array_unique($possible_urls);
  }

  /**
   * Check if two URLs match with robust normalization for scalability.
   * This method handles various URL formats and is future-proof for new pages.
   */
  private function urlsMatch($url1, $url2) {
    // Normalize URLs by removing trailing slashes and converting to lowercase
    $normalized1 = rtrim(strtolower(trim($url1)), '/');
    $normalized2 = rtrim(strtolower(trim($url2)), '/');
    
    // Direct match
    if ($normalized1 === $normalized2) {
      return true;
    }
    
    // Extract paths for comparison (ignore query parameters and fragments)
    $path1 = parse_url($normalized1, PHP_URL_PATH) ?: '/';
    $path2 = parse_url($normalized2, PHP_URL_PATH) ?: '/';
    
    // Normalize paths
    $path1 = rtrim($path1, '/') ?: '/';
    $path2 = rtrim($path2, '/') ?: '/';
    
    return $path1 === $path2;
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

  /**
   * Displays a comprehensive accessibility report with cards and URL list.
   *
   * @return array
   *   A render array for the comprehensive report page.
   */
  public function comprehensiveReport() {
    // Get aggregated statistics from cache service
    $aggregated_stats = $this->cacheService->getAggregatedStats();
    
    // Get detailed statistics from cache service
    $detailed_stats = $this->cacheService->getDetailedStats();
    
    // Get URLs from both scan button locations and block placements
    $scanned_urls = $this->getAccessibleUrlsFromCache();
    
    return [
      '#theme' => 'accessibility_comprehensive_report',
      '#violation_stats' => [
        'critical' => $aggregated_stats['critical_violations'] ?? 0,
        'serious' => $aggregated_stats['serious_violations'] ?? 0,
        'moderate' => $aggregated_stats['moderate_violations'] ?? 0,
        'minor' => $aggregated_stats['minor_violations'] ?? 0,
      ],
      '#total_violations' => $aggregated_stats['total_violations'] ?? 0,
      '#unique_pages' => $aggregated_stats['unique_urls_scanned'] ?? 0,
      '#scanned_urls' => $scanned_urls,
      '#attached' => [
        'library' => [
          'accessibility/comprehensive_report',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => ['accessibility:comprehensive_report', 'config:block.block', 'accessibility_scan_data'],
      ],
    ];
  }

  /**
   * Displays the cached accessibility data management page.
   *
   * @return array
   *   A render array for the cache management page.
   */
  public function cacheManagement() {
    // Get aggregated stats
    $aggregated_stats = $this->cacheService->getAggregatedStats();
    
    // Get detailed stats by URL
    $detailed_stats = $this->cacheService->getDetailedStats();
    
    // Get scan button URLs
    $scan_button_urls = $this->cacheService->getScanButtonUrls();
    
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['accessibility-cache-management']],
    ];
    
    // Add CSS
    $build['#attached']['library'][] = 'accessibility/cache_management';
    
    // Summary statistics
    $build['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache Summary'),
      '#open' => TRUE,
      'stats' => [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          $this->t('Total URLs with scan buttons: @count', ['@count' => count($scan_button_urls)]),
          $this->t('Total URLs scanned: @count', ['@count' => $aggregated_stats['unique_urls_scanned'] ?? 0]),
          $this->t('Total violations found: @count', ['@count' => $aggregated_stats['total_violations'] ?? 0]),
          $this->t('Critical violations: @count', ['@count' => $aggregated_stats['critical_violations'] ?? 0]),
          $this->t('Serious violations: @count', ['@count' => $aggregated_stats['serious_violations'] ?? 0]),
          $this->t('Moderate violations: @count', ['@count' => $aggregated_stats['moderate_violations'] ?? 0]),
          $this->t('Minor violations: @count', ['@count' => $aggregated_stats['minor_violations'] ?? 0]),
        ],
      ],
    ];
    
    // Scan button URLs
    if (!empty($scan_button_urls)) {
      $build['scan_buttons'] = [
        '#type' => 'details',
        '#title' => $this->t('URLs with Scan Buttons (@count)', ['@count' => count($scan_button_urls)]),
        '#open' => FALSE,
        'list' => [
          '#theme' => 'item_list',
          '#list_type' => 'ol',
          '#items' => array_map(function($url) {
            return $url;
          }, $scan_button_urls),
        ],
      ];
    }
    
    // Detailed scan results
    if (!empty($detailed_stats)) {
      $build['detailed_results'] = [
        '#type' => 'details',
        '#title' => $this->t('Detailed Scan Results (@count URLs)', ['@count' => count($detailed_stats)]),
        '#open' => TRUE,
      ];
      
      foreach ($detailed_stats as $url => $stats) {
        $build['detailed_results'][$url] = [
          '#type' => 'details',
          '#title' => $url,
          '#open' => FALSE,
          'info' => [
            '#type' => 'container',
            'last_scanned' => [
              '#markup' => '<p><strong>Last scanned:</strong> ' . date('Y-m-d H:i:s', $stats['last_scanned']) . '</p>',
            ],
            'violations_summary' => [
              '#markup' => '<p><strong>Violations:</strong> ' . 
                          'Total: ' . $stats['violation_counts']['total'] . ', ' .
                          'Critical: ' . $stats['violation_counts']['critical'] . ', ' .
                          'Serious: ' . $stats['violation_counts']['serious'] . ', ' .
                          'Moderate: ' . $stats['violation_counts']['moderate'] . ', ' .
                          'Minor: ' . $stats['violation_counts']['minor'] . '</p>',
            ],
          ],
        ];
        
        // Add violations details
        if (!empty($stats['violations'])) {
          $violation_items = [];
          foreach ($stats['violations'] as $violation) {
            $violation_items[] = [
              '#markup' => '<strong>' . $violation['impact'] . ':</strong> ' . $violation['help'] . 
                          ($violation['helpUrl'] ? ' (<a href="' . $violation['helpUrl'] . '" target="_blank">Documentation</a>)' : '') .
                          ' - ' . $violation['nodes'] . ' elements affected',
            ];
          }
          
          $build['detailed_results'][$url]['violations'] = [
            '#type' => 'details',
            '#title' => $this->t('Violations (@count)', ['@count' => count($stats['violations'])]),
            '#open' => FALSE,
            'list' => [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#items' => $violation_items,
            ],
          ];
        }
      }
    }
    
    // Cache management actions
    $build['actions'] = [
      '#type' => 'actions',
      'clear_all' => [
        '#type' => 'submit',
        '#value' => $this->t('Clear All Cache'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'onclick' => 'return confirm("Are you sure you want to clear all cached accessibility data?");',
        ],
        '#submit' => ['::clearAllCache'],
      ],
    ];
    
    return $build;
  }

  /**
   * Get URLs that should be accessible for scanning based on cache data and block placements.
   *
   * @return array
   *   Array of URL data objects.
   */
  private function getAccessibleUrlsFromCache() {
    $urls = [];
    
    // Priority 1: Get URLs based on where the Axe Scan block is placed
    $block_urls = $this->getUrlsFromBlockPlacements();
    
    // Priority 2: Get URLs that have scan buttons (from cache)
    $scan_button_urls = $this->cacheService->getScanButtonUrls();
    
    // Priority 3: Get URLs that have been scanned (from cache)
    $scanned_urls = $this->cacheService->getScannedUrls();
    
    // If we have block-defined URLs, use only those (respect admin configuration)
    if (!empty($block_urls)) {
      foreach ($block_urls as $url) {
        $scan_results = $this->cacheService->getScanResults($url);
        
        $urls[$url] = (object) [
          'scanned_url' => $url,
          'violation_count' => $scan_results ? $scan_results['violation_counts']['total'] : 0,
          'last_scan' => $scan_results ? date('Y-m-d H:i:s', $scan_results['scan_timestamp']) : null,
        ];
      }
    }
    // If no blocks are configured, use scan button URLs or scanned URLs
    elseif (!empty($scan_button_urls)) {
      foreach ($scan_button_urls as $url) {
        $scan_results = $this->cacheService->getScanResults($url);
        
        $urls[$url] = (object) [
          'scanned_url' => $url,
          'violation_count' => $scan_results ? $scan_results['violation_counts']['total'] : 0,
          'last_scan' => $scan_results ? date('Y-m-d H:i:s', $scan_results['scan_timestamp']) : null,
        ];
      }
    }
    // Final fallback: Use any scanned URLs
    else {
      foreach ($scanned_urls as $url) {
        $scan_results = $this->cacheService->getScanResults($url);
        
        if ($scan_results) {
          $urls[$url] = (object) [
            'scanned_url' => $url,
            'violation_count' => $scan_results['violation_counts']['total'],
            'last_scan' => date('Y-m-d H:i:s', $scan_results['scan_timestamp']),
          ];
        }
      }
    }
    
    // Sort by URL for consistent display
    ksort($urls);
    
    return array_values($urls);
  }

  /**
   * Get URLs that should be accessible for scanning based on block placements and existing scans.
   *
   * @return array
   *   Array of URL data objects.
   */
  private function getAccessibleUrls() {
    $urls = [];
    
    // Priority 1: Get URLs based on where the Axe Scan block is placed
    $block_urls = $this->getUrlsFromBlockPlacements();
    
    // If we have block-defined URLs, use only those (respect admin configuration)
    if (!empty($block_urls)) {
      foreach ($block_urls as $url) {
        $urls[$url] = (object) [
          'scanned_url' => $url,
          'violation_count' => 0,
          'last_scan' => null,
        ];
      }
      
      // Only add scan data for URLs that are in the block placement list
      $scanned_query = $this->database->select('accessibility_violations', 'av')
        ->fields('av', ['scanned_url'])
        ->condition('scanned_url', $block_urls, 'IN')
        ->groupBy('av.scanned_url')
        ->orderBy('scanned_url');
      
      $scanned_query->addExpression('COUNT(*)', 'violation_count');
      $scanned_query->addExpression('MAX(timestamp)', 'last_scan');
      $scanned_results = $scanned_query->execute()->fetchAll();
      
      // Update URLs with actual scan data
      foreach ($scanned_results as $result) {
        if (isset($urls[$result->scanned_url])) {
          $urls[$result->scanned_url] = $result;
        }
      }
    }
    else {
      // Fallback: If no blocks are configured, show all scanned URLs
      $scanned_query = $this->database->select('accessibility_violations', 'av')
        ->fields('av', ['scanned_url'])
        ->groupBy('av.scanned_url')
        ->orderBy('scanned_url');
      
      $scanned_query->addExpression('COUNT(*)', 'violation_count');
      $scanned_query->addExpression('MAX(timestamp)', 'last_scan');
      $scanned_results = $scanned_query->execute()->fetchAll();
      
      foreach ($scanned_results as $result) {
        $urls[$result->scanned_url] = $result;
      }
      
      // If still no URLs, use minimal defaults
      if (empty($urls)) {
        $base_url = \Drupal::request()->getSchemeAndHttpHost();
        $default_urls = [
          $base_url . '/accessibility/test-violations',
        ];
        
        foreach ($default_urls as $url) {
          $urls[$url] = (object) [
            'scanned_url' => $url,
            'violation_count' => 0,
            'last_scan' => null,
          ];
        }
      }
    }
    
    return array_values($urls);
  }

  /**
   * Get URLs from pages where the Axe Scan block is placed.
   *
   * @return array
   *   Array of URLs where the block is visible.
   */
  private function getUrlsFromBlockPlacements() {
    $urls = [];
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    
    try {
      // Load all block configurations to find axe scan blocks
      $block_storage = \Drupal::entityTypeManager()->getStorage('block');
      
      // Try to find blocks by plugin ID
      $axe_blocks = $block_storage->loadByProperties(['plugin' => 'axe_scan_block']);
      
      // If no axe_scan_block found, try to find any block that might contain axe scan functionality
      if (empty($axe_blocks)) {
        $all_blocks = $block_storage->loadMultiple();
        foreach ($all_blocks as $block) {
          $plugin_id = $block->getPluginId();
          if (strpos($plugin_id, 'axe') !== false || strpos($plugin_id, 'accessibility') !== false) {
            $axe_blocks[$block->id()] = $block;
          }
        }
      }
      
      foreach ($axe_blocks as $block) {
        /** @var \Drupal\block\Entity\Block $block */
        if ($block->status()) {
          $visibility = $block->getVisibility();
          
          // If no visibility restrictions, assume it appears on key pages
          if (empty($visibility)) {
            $urls[] = $base_url . '/';
            $urls[] = $base_url . '/accessibility/test-violations';
            // Add any existing scanned URLs
            $scanned_urls = $this->getExistingScannedUrls();
            $urls = array_merge($urls, $scanned_urls);
          }
          else {
            // Check path-based visibility
            if (isset($visibility['request_path'])) {
              $path_config = $visibility['request_path'];
              $pages = $path_config['pages'] ?? '';
              $negate = $path_config['negate'] ?? false;
              
              if (!empty($pages)) {
                $paths = preg_split('/\r\n|\r|\n/', $pages);
                foreach ($paths as $path) {
                  $path = trim($path);
                  if ($path) {
                    if ($path === '<front>') {
                      $urls[] = $base_url . '/';
                    } elseif (strpos($path, '*') === false) {
                      $clean_path = ltrim($path, '/');
                      $urls[] = $base_url . '/' . $clean_path;
                    } else {
                      // Handle wildcard paths - expand common ones
                      if ($path === '/*' || $path === '*') {
                        $urls[] = $base_url . '/';
                        $urls[] = $base_url . '/accessibility/test-violations';
                      } elseif (strpos($path, 'accessibility') !== false) {
                        $urls[] = $base_url . '/accessibility/test-violations';
                      }
                    }
                  }
                }
              }
            }
            
            // Check node type visibility (if block appears on specific content types)
            if (isset($visibility['node_type'])) {
              // Add sample node URLs - in a real scenario you'd query for actual nodes
              $urls[] = $base_url . '/node/1';
            }
            
            // Check user role visibility
            if (isset($visibility['user_role'])) {
              // If visible to certain roles, include common admin pages
              $urls[] = $base_url . '/admin';
              $urls[] = $base_url . '/user';
            }
          }
        }
      }
      
      // Log what we found for debugging
      \Drupal::logger('accessibility')->info('Found @count axe blocks, generated @url_count URLs', [
        '@count' => count($axe_blocks),
        '@url_count' => count($urls)
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->warning('Error loading block placements: @error', ['@error' => $e->getMessage()]);
    }
    
    // Remove duplicates
    $urls = array_unique($urls);
    
    // If still no URLs found, use intelligent defaults based on where scan functionality typically appears
    if (empty($urls)) {
      $urls = $this->getIntelligentDefaultUrls();
    }
    
    return $urls;
  }

  /**
   * Get existing scanned URLs from the database.
   *
   * @return array
   *   Array of URLs that have been scanned before.
   */
  private function getExistingScannedUrls() {
    $urls = [];
    try {
      $query = $this->database->select('accessibility_violations', 'av')
        ->fields('av', ['scanned_url'])
        ->groupBy('scanned_url')
        ->execute();
      
      foreach ($query as $row) {
        $urls[] = $row->scanned_url;
      }
    } catch (\Exception $e) {
      \Drupal::logger('accessibility')->warning('Error getting scanned URLs: @error', ['@error' => $e->getMessage()]);
    }
    
    return $urls;
  }

  /**
   * Get intelligent default URLs where scan functionality would typically be used.
   *
   * @return array
   *   Array of default URLs.
   */
  private function getIntelligentDefaultUrls() {
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    
    return [
      $base_url . '/',
      $base_url . '/accessibility/test-violations',
      $base_url . '/admin',
      $base_url . '/user',
    ];
  }

  /**
   * Get cached violation data for a specific slug.
   *
   * @param string $slug
   *   The slug to find cached data for.
   *
   * @return array|null
   *   The cached data or null if not found.
   */
  private function getCachedDataForSlug($slug) {
    // Get all scanned URLs from cache
    $scanned_urls = $this->cacheService->getScannedUrls();
    
    // Debug logging
    $this->getLogger('accessibility')->info('Looking for slug: @slug', ['@slug' => $slug]);
    $this->getLogger('accessibility')->info('Available scanned URLs: @urls', ['@urls' => implode(', ', $scanned_urls)]);
    
    // Strategy 1: Try both slug conversion methods
    foreach ($scanned_urls as $url) {
      $controller_slug = $this->convertUrlToSlug($url);
      $template_slug = $this->convertUrlToSlugTemplateStyle($url);
      
      if ($slug === $controller_slug || $slug === $template_slug) {
        $cached_data = $this->cacheService->getScanResults($url);
        $this->getLogger('accessibility')->info('Found matching cached data for @url via slug match', ['@url' => $url]);
        return $cached_data;
      }
    }
    
    // Strategy 2: Try common slug variations and reverse mappings
    $slug_variations = [
      $slug,
      str_replace('-', '/', $slug),
      '/' . $slug,
      $slug . '/',
      '/' . $slug . '/',
    ];
    
    // Special handling for known patterns - both directions
    if ($slug === 'main-page' || $slug === 'home') {
      $slug_variations[] = '';
      $slug_variations[] = '/';
      $slug_variations[] = 'home';
      $slug_variations[] = 'main-page';
    }
    
    // Strategy 3: Direct slug-to-slug matching (bypass URL conversion)
    foreach ($scanned_urls as $url) {
      $url_slug = $this->convertUrlToSlug($url);
      
      // Check if either slug matches the other (bidirectional)
      if ($slug === $url_slug || 
          ($slug === 'main-page' && $url_slug === 'home') ||
          ($slug === 'home' && $url_slug === 'main-page')) {
        $cached_data = $this->cacheService->getScanResults($url);
        $this->getLogger('accessibility')->info('Found matching cached data via direct slug match for @url', ['@url' => $url]);
        return $cached_data;
      }
    }
    
    foreach ($scanned_urls as $url) {
      $path = parse_url($url, PHP_URL_PATH);
      $path = $path ? trim($path, '/') : '';
      
      foreach ($slug_variations as $variation) {
        $variation = $variation ? trim($variation, '/') : '';
        if ($path === $variation) {
          $cached_data = $this->cacheService->getScanResults($url);
          $this->getLogger('accessibility')->info('Found matching cached data via variation for @url', ['@url' => $url]);
          return $cached_data;
        }
      }
    }
    
    $this->getLogger('accessibility')->warning('No cached data found for slug: @slug', ['@slug' => $slug]);
    return null;
  }

  /**
   * Convert a URL to a slug format (reverse of template logic).
   *
   * @param string $url
   *   The URL to convert.
   *
   * @return string
   *   The slug.
   */
  private function convertUrlToSlug($url) {
    // Extract path from URL
    $path_part = parse_url($url, PHP_URL_PATH);
    if (!$path_part || $path_part === '/') {
      return 'home';
    }
    
    // Remove leading slash and convert to slug
    $path_part = ltrim($path_part, '/');
    $slug = strtolower($path_part);
    $slug = str_replace(['/', '_', ' ', '.'], '-', $slug);
    $slug = preg_replace('/--+/', '-', $slug); // Remove multiple hyphens
    $slug = preg_replace('/\.(html|php|htm)$/', '', $slug); // Remove file extensions
    $slug = trim($slug, '-');
    
    return $slug ?: 'home';
  }

  /**
   * Convert a URL to a slug using the same logic as the template.
   *
   * @param string $url
   *   The URL to convert.
   *
   * @return string
   *   The slug matching template generation.
   */
  private function convertUrlToSlugTemplateStyle($url) {
    // Extract path part after domain (matching template logic)
    $url_parts = explode('//', $url);
    if (count($url_parts) < 2) {
      return 'home';
    }
    
    $domain_and_path = $url_parts[1];
    $path_parts = explode('/', $domain_and_path);
    
    // Remove domain (first element) and get path parts
    array_shift($path_parts);
    $path_part = implode('/', $path_parts);
    
    if (empty($path_part)) {
      return 'home';
    }
    
    // Apply template slug generation logic
    $page_slug = strtolower($path_part);
    $page_slug = str_replace(['/', '_', ' ', '.'], '-', $page_slug);
    $page_slug = str_replace('--', '-', $page_slug);
    $page_slug = trim($page_slug, '-');
    
    // Handle edge cases: remove common file extensions
    $page_slug = str_replace(['.html', '.php', '.htm'], '', $page_slug);
    
    return $page_slug ?: 'home';
  }

  /**
   * Convert a slug path back to a friendly title.
   *
   * @param string $path
   *   The path/slug to convert.
   *
   * @return string
   *   The friendly title.
   */
  private function convertSlugToTitle($path) {
    // Remove leading slash and trim
    $slug = trim($path, '/');
    
    // Handle root/home page
    if (empty($slug) || $slug === 'home' || $slug === 'main-page') {
      return 'Main Page';
    }
    
    // Convert common slug patterns back to titles
    $title_map = [
      'accessibility-test-violations-page' => 'Accessibility Test Violations Page',
      'accessibility-test-violations' => 'Accessibility Test Violations Page',
      'main-page' => 'Main Page',
      'home' => 'Main Page',
    ];
    
    // Check for exact matches first
    if (isset($title_map[$slug])) {
      return $title_map[$slug];
    }
    
    // Generic conversion: replace hyphens with spaces and title case
    $title = str_replace(['-', '_'], ' ', $slug);
    $title = ucwords($title);
    
    // Add "Page" suffix if it doesn't already end with "Page"
    if (!preg_match('/\bpage$/i', $title)) {
      $title .= ' Page';
    }
    
    return $title;
  }

  /**
   * Displays a test page with various accessibility violations.
   *
   * This page is intentionally riddled with accessibility issues
   * for testing purposes with axe-core and other scanning tools.
   *
   * @return array
   *   A render array for the test violations page.
   */
  public function testViolations() {
    return [
      '#theme' => 'accessibility_test_violations',
      '#attached' => [
        'library' => [
          'accessibility/axe_scanner',
          'accessibility/test_violations_styles',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
