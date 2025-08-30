<?php

/**
 * @file
 * Contains \Drupal\accessibility\Controller\LlmTestController.
 *
 * Controller for LLM testing functionality.
 */

namespace Drupal\accessibility\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\accessibility\Service\LlmAnalysisService;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for LLM accessibility testing.
 */
class LlmTestController extends ControllerBase {
  use StringTranslationTrait;

  // Configuration constants
  const MAX_CONTENT_LENGTH = 2097152; // 2MB
  const RATE_LIMIT_WINDOW = 3600; // 1 hour
  const RATE_LIMIT_MAX_REQUESTS = 50;

  /**
   * The LLM analysis service.
   *
   * @var \Drupal\accessibility\Service\LlmAnalysisService
   */
  protected $llmService;

  protected $requestStack;
  protected $formBuilder;
  protected $keyValue;
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new LlmTestController object.
   */
  public function __construct(
    LlmAnalysisService $llm_service,
    RequestStack $request_stack,
    FormBuilderInterface $form_builder,
    KeyValueFactoryInterface $key_value_factory,
    $logger,
    $time
  ) {
    $this->llmService = $llm_service;
    $this->requestStack = $request_stack;
    $this->formBuilder = $form_builder;
    $this->keyValue = $key_value_factory->get('accessibility_llm_test');
    $this->logger = $logger;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('accessibility.llm_service'),
      $container->get('request_stack'),
      $container->get('form_builder'),
      $container->get('keyvalue'),
      $container->get('logger.factory')->get('accessibility'),
      $container->get('datetime.time')
    );
  }

  /**
   * Page callback for the LLM test page.
   */
  public function testPage() {
    $build = [];
    $build['form'] = $this->formBuilder->getForm('\Drupal\accessibility\Form\LlmTestForm');
    return $build;
  }

  /**
   * Handles AJAX form submission.
   */
  public function handleAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $config = $this->config('accessibility.settings');
    $request = $this->requestStack->getCurrentRequest();

    // Request validation
    if (!$request->isXmlHttpRequest()) {
      $this->logger->warning('Non-AJAX request detected');
      return $this->buildErrorResponse($response, $this->t('AJAX requests only'));
    }

    if (!$config->get('llm_enabled')) {
      $this->logger->error('LLM analysis disabled');
      return $this->buildErrorResponse($response, $this->t('LLM analysis is disabled'));
    }

    if (!$this->checkRateLimit()) {
      $this->logger->warning('Rate limit exceeded', ['uid' => $this->currentUser()->id()]);
      return $this->buildErrorResponse($response, $this->t('Too many requests. Try again later.'), 429);
    }

    // Process input
    $html_content = trim($form_state->getValue('html_content', ''));

    if (empty($html_content)) {
      return $this->buildErrorResponse($response, $this->t('Please provide HTML content'));
    }

    if (mb_strlen($html_content) > self::MAX_CONTENT_LENGTH) {
      $this->logger->warning('Content size limit exceeded');
      return $this->buildErrorResponse($response, $this->t('Content exceeds @sizeMB limit', [
        '@size' => number_format(self::MAX_CONTENT_LENGTH / 1048576, 1),
      ]));
    }

    try {
      $html_content = $this->sanitizeInput($html_content);
      $this->showLoadingIndicator($response);

      $start_time = microtime(TRUE);
      set_time_limit(min(300, (int)ini_get('max_execution_time') ?: 300));

      $analysis = $this->llmService->analyzeAccessibility($html_content);
      $duration = microtime(TRUE) - $start_time;

      $this->logAnalysisMetrics($duration, mb_strlen($html_content));
      $this->buildResultsResponse($response, $analysis, $duration);
      $response->addCommand(new InvokeCommand('#llm-test-form', 'addClass', ['form--success']));

    } catch (\Exception $e) {
      $error_id = uniqid('llm-err-', TRUE);
      $this->logger->error('Analysis failed: @msg (@id)', [
        '@msg' => $e->getMessage(),
        '@id' => $error_id,
        'exception' => $e,
      ]);
      return $this->buildErrorResponse($response, $this->t('Analysis failed (ID: @id)', ['@id' => $error_id]));
    }

    return $response;
  }

  protected function sanitizeInput($input) {
    return trim(preg_replace([
      '@<(script|style)[^>]*?>.*?</\\1>@si', // Remove script/style tags
      '/<!--.*?-->/s', // Remove HTML comments
    ], '', html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  }

  protected function sanitizeOutput($output) {
    return check_markup($output, 'basic_html');
  }

  protected function checkRateLimit() {
    $key = sprintf('rate_limit:%s:%s',
      $this->currentUser()->id() ?: 'anon',
      md5($this->requestStack->getCurrentRequest()->getClientIp())
    );

    $window = $this->time->getRequestTime() - self::RATE_LIMIT_WINDOW;
    $requests = array_filter(
      $this->keyValue->get($key, []),
      fn($time) => $time > $window
    );

    if (count($requests) >= self::RATE_LIMIT_MAX_REQUESTS) {
      return FALSE;
    }

    $requests[] = $this->time->getRequestTime();
    $this->keyValue->set($key, $requests);
    return TRUE;
  }

  protected function showLoadingIndicator(AjaxResponse $response) {
    $response->addCommand(new HtmlCommand('#llm-results-wrapper', [
      '#type' => 'container',
      '#attributes' => ['class' => ['llm-loading']],
      'content' => [
        '#markup' => '<div class="spinner"></div><div class="message">' .
          $this->t('Analyzing...') . '</div>',
      ],
    ]));
  }

  protected function logAnalysisMetrics(float $duration, int $contentLength) {
    $this->logger->info('Analysis completed in @time s for @bytes bytes', [
      '@time' => number_format($duration, 2),
      '@bytes' => $contentLength,
    ]);
  }

  protected function buildResultsResponse(AjaxResponse $response, array $analysis, float $duration) {
    $results = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['llm-results'],
        'data-timestamp' => time(),
      ],
      'meta' => [
        '#markup' => $this->t('Analysis completed in @time s', [
          '@time' => number_format($duration, 2),
        ]),
      ],
    ];

    foreach (['issues' => 'Identified Issues', 'recommendations' => 'Recommendations'] as $key => $title) {
      if (!empty($analysis[$key])) {
        $results[$key] = [
          '#type' => 'details',
          '#title' => $this->t($title),
          '#open' => TRUE,
          'list' => [
            '#theme' => 'item_list',
            '#items' => array_map([$this, 'sanitizeOutput'], (array) $analysis[$key]),
            '#attributes' => ['class' => ["llm-{$key}"]],
          ],
        ];
      }
    }

    $response->addCommand(new HtmlCommand('#llm-results-wrapper', $results));
  }

  protected function buildErrorResponse(AjaxResponse $response, $message, $status_code = 400) {
    $response->setStatusCode($status_code);
    $response->addCommand(new InvokeCommand('#llm-test-form', 'addClass', ['form--error']));

    $response->addCommand(new HtmlCommand('#llm-results-wrapper', [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['messages', 'messages--error'],
        'role' => 'alert',
      ],
      'content' => ['#markup' => '<div class="message">' . $message . '</div>'],
    ]));

    $response->addCommand(new InvokeCommand('#llm-results-wrapper .messages--error', 'focus'));
    return $response;
  }
}
