<?php

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\accessibility\Service\ChatbotService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for handling chatbot requests.
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
   * Handle AJAX chatbot requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function handleChatbotRequest(Request $request) {
    // Check if the user has permission
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied.',
      ], 403);
    }

    $violation_id = $request->request->get('violation_id');
    $violation_description = $request->request->get('violation_description');
    $user_question = $request->request->get('user_question', '');

    if (empty($violation_id) || empty($violation_description)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Missing required parameters.',
      ], 400);
    }

    $result = $this->chatbotService->getAccessibilitySolution(
      $violation_id,
      $violation_description,
      $user_question
    );

    return new JsonResponse($result);
  }

  /**
   * Handle AJAX request to get chatbot solution for a violation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function getChatbotSolution(Request $request) {
    $response = new AjaxResponse();

    // Check if the user has permission
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      $response->addCommand(new HtmlCommand('.chatbot-response', 
        '<div class="error">Access denied.</div>'));
      return $response;
    }

    $violation_id = $request->request->get('violation_id');
    $violation_description = $request->request->get('violation_description');
    $user_question = $request->request->get('user_question', '');
    $chatbot_container_id = $request->request->get('container_id');

    if (empty($violation_id) || empty($violation_description) || empty($chatbot_container_id)) {
      $response->addCommand(new HtmlCommand("#{$chatbot_container_id} .chatbot-response", 
        '<div class="error">Missing required parameters.</div>'));
      return $response;
    }

    // Show loading state
    $response->addCommand(new HtmlCommand("#{$chatbot_container_id} .chatbot-response", 
      '<div class="loading">Getting AI solution...</div>'));

    $result = $this->chatbotService->getAccessibilitySolution(
      $violation_id,
      $violation_description,
      $user_question
    );

    if ($result['success']) {
      $solution_html = '<div class="chatbot-solution">';
      $solution_html .= '<div class="solution-header">💡 AI Accessibility Solution</div>';
      $solution_html .= '<div class="solution-content">' . nl2br(htmlspecialchars($result['solution'])) . '</div>';
      if (isset($result['model_used'])) {
        $solution_html .= '<div class="solution-footer">Powered by ' . htmlspecialchars($result['model_used']) . '</div>';
      }
      $solution_html .= '</div>';
      
      $response->addCommand(new HtmlCommand("#{$chatbot_container_id} .chatbot-response", $solution_html));
    }
    else {
      $error_html = '<div class="error">⚠️ ' . htmlspecialchars($result['error']) . '</div>';
      $response->addCommand(new HtmlCommand("#{$chatbot_container_id} .chatbot-response", $error_html));
    }

    // Hide the loading state and show the response
    $response->addCommand(new InvokeCommand("#{$chatbot_container_id} .chatbot-response", 'removeClass', ['loading']));

    return $response;
  }

}
