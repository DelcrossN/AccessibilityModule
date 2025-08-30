<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\ChatbotController.
 *
 * Controller for handling chatbot functionality.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\accessibility\Service\ChatbotService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling chatbot AJAX requests.
 */
class ChatbotController extends ControllerBase {

  /**
   * The chatbot service.
   *
   * @var \Drupal\accessibility\Service\ChatbotService
   */
  protected $chatbotService;

  /**
   * Constructs a new ChatbotController object.
   *
   * @param \Drupal\accessibility\Service\ChatbotService $chatbot_service
   *   The chatbot service.
   */
  public function __construct(ChatbotService $chatbot_service) {
    $this->chatbotService = $chatbot_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('accessibility.chatbot_service')
    );
  }

  /**
   * Handle AJAX requests for chatbot functionality.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with chatbot solution or error.
   */
  public function handleRequest(Request $request) {
    // Validate that this is an AJAX request
    if (!$request->isXmlHttpRequest()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request method.',
      ], 400);
    }

    // Get request parameters
    $violation_id = $request->request->get('violation_id');
    $violation_description = $request->request->get('violation_description');
    $user_question = $request->request->get('user_question', '');

    // Validate required parameters
    if (empty($violation_id) || empty($violation_description)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Missing required parameters.',
      ], 400);
    }

    // Sanitize inputs
    $violation_id = strip_tags(trim($violation_id));
    $violation_description = strip_tags(trim($violation_description));
    $user_question = strip_tags(trim($user_question));

    // Check if the chatbot service is configured
    if (!$this->chatbotService->isConfigured()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Chatbot service is not properly configured. Please check the admin settings.',
      ], 503);
    }

    try {
      // Get the solution from the chatbot service
      $result = $this->chatbotService->getAccessibilitySolution(
        $violation_id,
        $violation_description,
        $user_question
      );

      // Return the result as JSON
      return new JsonResponse($result);
    }
    catch (\Exception $e) {
      // Log the error
      $this->getLogger('accessibility')->error('Chatbot controller error: @message', [
        '@message' => $e->getMessage(),
      ]);

      // Return a generic error response
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An unexpected error occurred while processing your request.',
      ], 500);
    }
  }

}
