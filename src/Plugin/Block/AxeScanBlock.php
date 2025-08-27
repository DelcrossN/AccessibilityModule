<?php

namespace Drupal\accessibility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an 'Axe Scan' Block.
 *
 * @Block(
 *   id = "axe_scan_block",
 *   admin_label = @Translation("Run Axe Scan"),
 *   category = @Translation("Accessibility"),
 * )
 */
class AxeScanBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('accessibility.settings');
    
    // Create the button render array.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['axe-scan-block-wrapper'],
        'id' => 'axe-scan-block',
      ],
    ];

    // Add chatbot settings checkbox if chatbot is enabled globally
    if ($config->get('chatbot_enabled')) {
      $build['chatbot_option'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable AI Chatbot Help'),
        '#description' => $this->t('Show AI-powered accessibility solutions below each violation.'),
        '#default_value' => TRUE,
        '#attributes' => [
          'id' => 'enable-chatbot-help',
          'class' => ['chatbot-enable-checkbox'],
        ],
      ];
    }

    $build['button'] = [
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
    ];

    // Keep the results container but hide it via CSS since we're using popup now
    $build['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['axe-scan-results-container'],
        'id' => 'axe-scan-sidebar-results',
        'style' => 'display: none;',
      ],
    ];

    $build['#attached'] = [
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
              'enabled' => $config->get('chatbot_enabled'),
              'apiEndpoint' => $config->get('chatbot_api_endpoint'),
              'model' => $config->get('chatbot_model') ?: 'gpt-3.5-turbo',
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
    // Only show the block to users with proper permissions.
    return AccessResult::allowedIfHasPermission($account, 'administer site configuration');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Don't cache this block.
    return 0;
  }

}
