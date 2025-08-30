<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\AccessibilityTestController.
 *
 * Controller for testing accessibility functionality.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\accessibility\Service\AccessibilityCacheService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for testing accessibility functionality.
 */
class AccessibilityTestController extends ControllerBase {

  /**
   * The accessibility cache service.
   *
   * @var \Drupal\accessibility\Service\AccessibilityCacheService
   */
  protected $cacheService;

  /**
   * Constructs a new AccessibilityTestController object.
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
   * Test endpoint to simulate scan results.
   */
  public function testScan() {
    // Generate diverse fake scan data for multiple pages
    $test_pages = [
      '/home' => [
        'violations' => [
          [
            'id' => 'color-contrast',
            'impact' => 'serious',
            'description' => 'Elements must have sufficient color contrast',
            'help' => 'Ensure all text elements have sufficient color contrast',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/color-contrast',
            'tags' => ['cat.color', 'wcag2aa', 'wcag143'],
            'nodes' => [[], [], []], // 3 nodes
          ],
          [
            'id' => 'aria-label',
            'impact' => 'critical',
            'description' => 'Elements with ARIA labels must have valid text',
            'help' => 'Ensure ARIA labels are meaningful',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/aria-label',
            'tags' => ['cat.aria', 'wcag2a'],
            'nodes' => [[], []], // 2 nodes
          ],
          [
            'id' => 'heading-order',
            'impact' => 'moderate',
            'description' => 'Heading levels should only increase by one',
            'help' => 'Ensure headings follow a logical order',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/heading-order',
            'tags' => ['cat.semantics', 'wcag2a'],
            'nodes' => [[]], // 1 node
          ],
        ],
      ],
      '/about' => [
        'violations' => [
          [
            'id' => 'alt-text',
            'impact' => 'critical',
            'description' => 'Images must have alternative text',
            'help' => 'Ensure all images have alt text',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/image-alt',
            'tags' => ['cat.text-alternatives', 'wcag2a'],
            'nodes' => [[], [], [], []], // 4 nodes
          ],
          [
            'id' => 'label',
            'impact' => 'serious',
            'description' => 'Form elements must have labels',
            'help' => 'Ensure all form elements have proper labels',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/label',
            'tags' => ['cat.forms', 'wcag2a'],
            'nodes' => [[], []], // 2 nodes
          ],
          [
            'id' => 'link-name',
            'impact' => 'minor',
            'description' => 'Links must have discernible text',
            'help' => 'Ensure links have meaningful text',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/link-name',
            'tags' => ['cat.name-role-value', 'wcag2a'],
            'nodes' => [[], [], []], // 3 nodes
          ],
        ],
      ],
      '/contact' => [
        'violations' => [
          [
            'id' => 'keyboard',
            'impact' => 'serious',
            'description' => 'Elements must be keyboard accessible',
            'help' => 'Ensure all interactive elements are keyboard accessible',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/keyboard',
            'tags' => ['cat.keyboard', 'wcag2a'],
            'nodes' => [[]], // 1 node
          ],
          [
            'id' => 'focus-order',
            'impact' => 'moderate',
            'description' => 'Elements must have logical tab order',
            'help' => 'Ensure focus moves in logical order',
            'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.8/focus-order',
            'tags' => ['cat.keyboard', 'wcag2a'],
            'nodes' => [[], []], // 2 nodes
          ],
        ],
      ],
    ];

    // Cache scan results for all test pages
    foreach ($test_pages as $url => $scan_data) {
      $this->cacheService->cacheScanResults($url, $scan_data);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Test scan data cached successfully for ' . count($test_pages) . ' pages',
      'aggregated_stats' => $this->cacheService->getAggregatedStats(),
      'daily_counts' => $this->cacheService->getDailyScanCounts(),
    ]);
  }

  /**
   * Clear scan data for testing.
   */
  public function clearData() {
    \Drupal::cache()->delete('accessibility_daily_scan_counts');
    \Drupal::cache()->delete('accessibility_aggregated_stats');
    
    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Scan data cleared successfully',
    ]);
  }

}
