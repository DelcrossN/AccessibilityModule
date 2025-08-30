<?php

/**
 * @file
 * Contains \Drupal\accessibility\Plugin\Block\AxeScanBlock.
 *
 * Block plugin for accessibility scanning tools sidebar.
 */

namespace Drupal\accessibility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Accessibility Tools' Block.
 *
 * @Block(
 *   id = "axe_scan_block",
 *   admin_label = @Translation("Accessibility Tools"),
 *   category = @Translation("Accessibility"),
 * )
 */
class AxeScanBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AxeScanBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get accessibility configuration
    $config = $this->configFactory->get('accessibility.settings');
    
    // Create the button render array.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['axe-scan-block-wrapper'],
        'id' => 'axe-scan-block',
      ],
      'button' => [
        '#type' => 'button',
        '#value' => $this->t('Run Axe Scan'),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary', 
            'axe-scan-button',
            'js-axe-scan-trigger'
          ],
          'id' => 'run-axe-scan-sidebar',
          'data-scan-type' => 'sidebar',
        ],
      ],
      // Add chatbot opt-in checkbox if chatbot is enabled
      'chatbot_optin' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable AI-powered<br>accessibility help'),
        '#description_display' => 'after',
        '#default_value' => FALSE,
        '#attributes' => [
          'id' => 'enable-chatbot-help',
          'class' => ['chatbot-optin-checkbox'],
        ],
        '#access' => $config->get('chatbot_enabled', FALSE),
      ],
      // Keep the results container but hide it via CSS since we're using popup now
      'results' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['axe-scan-results-container'],
          'id' => 'axe-scan-sidebar-results',
          'style' => 'display: none;',
        ],
      ],
      '#attached' => [
        'library' => [
          'accessibility/axe_scan_sidebar',
        ],
        'drupalSettings' => [
          'accessibility' => [
            'axeScanBlock' => [
              'enabled' => TRUE,
              'scanUrl' => \Drupal::request()->getRequestUri(),
              'popupMode' => TRUE, // Flag to indicate we're using popup mode
              'chatbot' => [
                'enabled' => $config->get('chatbot_enabled', FALSE),
                'configured' => $config->get('chatbot_enabled', FALSE) && 
                               $config->get('chatbot_api_endpoint') && 
                               $config->get('chatbot_api_key'),
              ],
            ],
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Allow access if user has either admin permission or accessibility tools permission
    return AccessResult::allowedIfHasPermissions($account, [
      'administer site configuration',
      'use accessibility tools',
    ], 'OR');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Don't cache this block.
    return 0;
  }

}
