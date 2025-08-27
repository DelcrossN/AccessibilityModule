<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Controller for handling Axe accessibility reports.
 */
class AxeReportController extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * AxeReportController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
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
   * Saves axe scan results to the database.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function saveReport(Request $request) {
    // Get the JSON data from the request
    $data = json_decode($request->getContent(), TRUE);
    
    if (!$data || !isset($data['url']) || !isset($data['violations'])) {
      return new JsonResponse(['error' => 'Invalid data'], 400);
    }

    $url = $data['url'];
    $title = $data['title'] ?? 'Untitled Page';
    $violations = $data['violations'];
    
    // Count violations by severity
    $critical_count = 0;
    $serious_count = 0;
    $moderate_count = 0;
    $minor_count = 0;
    
    foreach ($violations as $violation) {
      $impact = $violation['impact'] ?? 'minor';
      switch ($impact) {
        case 'critical':
          $critical_count++;
          break;
        case 'serious':
          $serious_count++;
          break;
        case 'moderate':
          $moderate_count++;
          break;
        default:
          $minor_count++;
          break;
      }
    }

    $total_violations = count($violations);

    try {
      // Start a database transaction
      $transaction = $this->database->startTransaction();

      // Delete existing report for this URL
      $this->database->delete('accessibility_reports')
        ->condition('url', $url)
        ->execute();

      // Delete existing violations for this URL
      $this->database->delete('accessibility_violations')
        ->condition('url', $url)
        ->execute();

      // Insert new report summary
      $this->database->insert('accessibility_reports')
        ->fields([
          'url' => $url,
          'title' => $title,
          'violation_count' => $total_violations,
          'critical_count' => $critical_count,
          'serious_count' => $serious_count,
          'moderate_count' => $moderate_count,
          'minor_count' => $minor_count,
          'last_scanned' => time(),
        ])
        ->execute();

      // Insert individual violations
      foreach ($violations as $violation) {
        $impact = $violation['impact'] ?? 'minor';
        $impact_weight = $this->getImpactWeight($impact);
        
        $this->database->insert('accessibility_violations')
          ->fields([
            'url' => $url,
            'rule_id' => $violation['id'] ?? '',
            'impact' => $impact,
            'impact_weight' => $impact_weight,
            'description' => $violation['description'] ?? '',
            'help' => $violation['help'] ?? '',
            'help_url' => $violation['helpUrl'] ?? '',
            'tags' => json_encode($violation['tags'] ?? []),
            'nodes_count' => count($violation['nodes'] ?? []),
            'nodes_data' => json_encode($violation['nodes'] ?? []),
            'scanned_url' => $url,
            'nodes' => serialize($violation['nodes'] ?? []),
            'timestamp' => time(),
          ])
          ->execute();
      }

      // Commit the transaction
      unset($transaction);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Report saved successfully',
        'summary' => [
          'total' => $total_violations,
          'critical' => $critical_count,
          'serious' => $serious_count,
          'moderate' => $moderate_count,
          'minor' => $minor_count,
        ],
      ]);
    }
    catch (\Exception $e) {
      // Rollback the transaction on error
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      
      \Drupal::logger('accessibility')->error('Failed to save accessibility report: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse(['error' => 'Failed to save report'], 500);
    }
  }

  /**
   * Gets the numeric weight for impact sorting.
   *
   * @param string $impact
   *   The impact level.
   *
   * @return int
   *   The numeric weight.
   */
  private function getImpactWeight($impact) {
    $weights = [
      'critical' => 1,
      'serious' => 2,
      'moderate' => 3,
      'minor' => 4,
    ];
    
    return $weights[$impact] ?? 4;
  }

}
