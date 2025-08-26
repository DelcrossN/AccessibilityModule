<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for accessibility test violations page.
 */
class AxeReportController extends ControllerBase {

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
   * Save accessibility scan report.
   */
  public function saveReport(Request $request) {
    // This method can be implemented later if needed
    // For now, return a simple response
    return new JsonResponse(['status' => 'success']);
  }

}
