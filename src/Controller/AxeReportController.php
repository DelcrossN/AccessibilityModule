<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;

class AxeReportController extends ControllerBase {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a new AxeReportController.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * Saves the AXE report to a file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function saveReport(Request $request) {
    $data = $request->getContent();
    if (empty($data)) {
      return new JsonResponse(['error' => 'No data received'], 400);
    }

    // Ensure the public files directory exists and is writable.
    $directory = 'public://axe-reports';
    if (!file_exists($directory)) {
      if (!\Drupal::service('file_system')->prepareDirectory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
        return new JsonResponse(['error' => 'Could not create directory for reports'], 500);
      }
    }

    $filename = 'axe-report-' . date('Y-m-d_H-i-s') . '.json';
    $filepath = $directory . '/' . $filename;
    $realpath = $this->streamWrapperManager->getViaUri($filepath)->realpath();

    // Save the file contents.
    if (file_put_contents($realpath, $data) === FALSE) {
      return new JsonResponse(['error' => 'Could not save report file'], 500);
    }

    // Create a file entity.
    $file = File::create([
      'uid' => $this->currentUser()->id(),
      'uri' => $filepath,
      'status' => 1,
      'filesize' => filesize($realpath),
      'filemime' => 'application/json',
      'filename' => $filename,
    ]);

    try {
      $file->save();
      return new JsonResponse([
        'message' => 'Report saved successfully',
        'filename' => $filename,
        'fid' => $file->id(),
      ]);
    } catch (\Exception $e) {
      return new JsonResponse(['error' => 'Could not save file entity: ' . $e->getMessage()], 500);
    }
  }
}
