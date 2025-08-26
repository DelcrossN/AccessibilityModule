/**
 * @file
 * Axe scan sidebar block functionality with sliding popup.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  let isScanning = false;
  let currentScan = null;
  let axeLoaded = false;

  /**
   * Behavior for the Axe Scan sidebar block.
   */
  Drupal.behaviors.axeScanSidebar = {
    attach: function (context, settings) {
      // Initialize the Axe scan button in the sidebar.
      once('axe-scan-sidebar-init', '.js-axe-scan-trigger', context).forEach(function(element) {
        const $button = $(element);
        
        $button.on('click', function(e) {
          e.preventDefault();
          
          if (!isScanning) {
            showPopupAndScan();
          }
        });
        
        // Initialize popup close events
        once('violations-popup-close', '#close-popup', context).forEach(function (button) {
          button.addEventListener('click', function (e) {
            e.preventDefault();
            hidePopup();
          });
        });

        once('violations-overlay-close', '.popup-overlay', context).forEach(function (overlay) {
          overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
              e.preventDefault();
              hidePopup();
            }
          });
        });

        // ESC key to close popup
        once('violations-esc-close', document, context).forEach(function (doc) {
          doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('violations-popup').classList.contains('active')) {
              hidePopup();
            }
          });
        });
      });
    }
  };

  function showPopupAndScan() {
    createPopup();
    
    const popup = document.getElementById('violations-popup');
    const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');

    if (!popup) return;

    // Show popup
    popup.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Update button and scanning state
    isScanning = true;
    updateButtonState(button, true);

    // Start scanning with a small delay to ensure popup is fully rendered
    setTimeout(() => {
      runAxeScan();
    }, 300);
  }

  function createPopup() {
    // Remove any existing popup
    const existingPopup = document.getElementById('violations-popup');
    if (existingPopup) {
      existingPopup.remove();
    }

    // Create popup HTML matching the reference design
    const popupHtml = `
      <div id="violations-popup" class="popup-overlay">
        <div class="popup-content">
          <div class="popup-header">
            <h3>Accessibility Scan Results</h3>
            <div class="popup-controls">
              <button id="minimize-popup" type="button" aria-label="Minimize accessibility results">
                ↙
              </button>
              <button id="close-popup" type="button" aria-label="Close accessibility results">
                ×
              </button>
            </div>
          </div>
          <div class="popup-body">
            <div id="violations-list">
              <!-- Results will be inserted here -->
            </div>
          </div>
        </div>
      </div>
    `;

    // Append to body
    document.body.insertAdjacentHTML('beforeend', popupHtml);

    // Bind close events using event delegation
    const popup = document.getElementById('violations-popup');
    const closeButton = document.getElementById('close-popup');
    const minimizeButton = document.getElementById('minimize-popup');
    
    if (closeButton) {
      closeButton.addEventListener('click', hidePopup);
    }
    
    if (minimizeButton) {
      minimizeButton.addEventListener('click', toggleMinimize);
    }
    
    if (popup) {
      popup.addEventListener('click', function(e) {
        if (e.target === popup) {
          hidePopup();
        }
      });
    }

    // Close on Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && popup && popup.classList.contains('active')) {
        hidePopup();
      }
    });
  }

  function toggleMinimize() {
    const popup = document.getElementById('violations-popup');
    const popupContent = popup ? popup.querySelector('.popup-content') : null;
    const minimizeButton = document.getElementById('minimize-popup');
    
    if (!popup || !popupContent || !minimizeButton) return;
    
    const isMinimized = popupContent.classList.contains('minimized');
    
    if (isMinimized) {
      // Maximize
      popupContent.classList.remove('minimized');
      popup.classList.remove('minimized');
      minimizeButton.innerHTML = '↙';
      minimizeButton.setAttribute('aria-label', 'Minimize accessibility results');
      minimizeButton.setAttribute('title', 'Minimize');
      // Restore overlay effect and prevent body scroll
      document.body.style.overflow = 'hidden';
    } else {
      // Minimize
      popupContent.classList.add('minimized');
      popup.classList.add('minimized');
      minimizeButton.innerHTML = '↗';
      minimizeButton.setAttribute('aria-label', 'Maximize accessibility results');
      minimizeButton.setAttribute('title', 'Maximize');
      // Remove overlay effect and allow body scroll
      document.body.style.overflow = '';
    }
  }

  function hidePopup() {
    const popup = document.getElementById('violations-popup');
    const popupContent = popup ? popup.querySelector('.popup-content') : null;
    const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');

    if (popup) {
      popup.classList.remove('active', 'minimized');
      document.body.style.overflow = '';
    }
    
    // Reset minimize state when closing
    if (popupContent) {
      popupContent.classList.remove('minimized');
    }
    
    const minimizeButton = document.getElementById('minimize-popup');
    if (minimizeButton) {
      minimizeButton.innerHTML = '⇙';
      minimizeButton.setAttribute('aria-label', 'Minimize accessibility results');
      minimizeButton.setAttribute('title', 'Minimize');
    }

    // Force reset button state and cancel any ongoing scan
    if (isScanning) {
      forceResetState();
    }
  }

  function updateButtonState(button, scanning) {
    if (!button) return;
    
    if (scanning) {
      button.classList.add('scanning');
      button.disabled = true;
      button.innerHTML = '<span class="scan-icon"></span> Scanning...';
    } else {
      button.classList.remove('scanning');
      button.disabled = false;
      button.innerHTML = 'Run Axe Scan';
    }
  }

  function resetButton() {
    const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');
    if (button) {
      isScanning = false;
      currentScan = null;
      updateButtonState(button, false);
    }
  }

  function forceResetState() {
    console.log('Force resetting scanner state...');
    isScanning = false;
    currentScan = null;

    // Try to cleanup axe if it exists
    if (typeof window.axe !== 'undefined' && window.axe.cleanup) {
      try {
        window.axe.cleanup();
      } catch (e) {
        console.log('Axe cleanup not available or failed:', e.message);
      }
    }

    const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');
    if (button) {
      updateButtonState(button, false);
    }
  }

  async function runAxeScan() {
    const violationsList = document.getElementById('violations-list');

    if (!violationsList) {
      resetButton();
      return;
    }

    try {
      // Show scanning message with CSS spinner
      violationsList.innerHTML = '<div class="scanning-message"><p><span class="scan-icon"></span> Scanning page for accessibility violations...</p></div>';

      // Force reset any existing axe state
      if (typeof window.axe !== 'undefined') {
        try {
          // Try to reset axe state
          if (window.axe.reset) {
            window.axe.reset();
          }
        } catch (e) {
          console.log('Axe reset not available:', e.message);
        }
      }

      // Load axe if needed
      if (!axeLoaded || typeof window.axe === 'undefined') {
        console.log('Loading axe-core...');
        await loadAxeCore();
        axeLoaded = true;
      }

      // Perform the scan
      await performScan();

    } catch (error) {
      console.error('Error in runAxeScan:', error);
      showError('Error during accessibility scan: ' + error.message);
      resetButton();
    }
  }

  function loadAxeCore() {
    return new Promise((resolve, reject) => {
      // Remove any existing axe script to ensure clean load
      const existingScript = document.querySelector('script[src*="axe"]');
      if (existingScript) {
        existingScript.remove();
      }

      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.8.2/axe.min.js';
      script.crossOrigin = 'anonymous';

      script.onload = function() {
        console.log('Axe-core loaded successfully');
        // Longer delay to ensure axe is fully initialized
        setTimeout(() => {
          if (typeof window.axe !== 'undefined') {
            resolve();
          } else {
            reject(new Error('Axe failed to initialize'));
          }
        }, 500);
      };

      script.onerror = function() {
        reject(new Error('Failed to load axe-core'));
      };

      document.head.appendChild(script);
    });
  }

  async function performScan() {
    try {
      console.log('Starting axe scan...');

      // Wait a bit more before scanning to ensure everything is ready
      await new Promise(resolve => setTimeout(resolve, 200));

      // Configure axe scan with timeout
      const scanOptions = {
        tags: ['wcag2a', 'wcag2aa', 'wcag21aa'],
        exclude: [['#violations-popup']],
        timeout: 30000 // 30 second timeout
      };

      // Create the scan promise with timeout handling
      currentScan = Promise.race([
        window.axe.run(document, scanOptions),
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Scan timeout')), 35000)
        )
      ]);

      const results = await currentScan;
      console.log('Axe scan completed:', results);
      displayResults(results);
      resetButton();

    } catch (error) {
      console.error('Axe scan error:', error);

      let errorMessage = 'Error during accessibility scan';

      if (error.message && error.message.includes('already running')) {
        errorMessage = 'Scanner is busy. Resetting and trying again...';

        // Force reset and retry once
        forceResetState();
        setTimeout(() => {
          if (!isScanning) { // Only retry if we're not already scanning again
            showPopupAndScan();
          }
        }, 1000);
        return;
      } else if (error.message && error.message.includes('timeout')) {
        errorMessage = 'Scan timed out. Please try again.';
      } else if (error.message) {
        errorMessage += ': ' + error.message;
      }

      showError(errorMessage);
      resetButton();
    }
  }

  function displayResults(results) {
    const violationsList = document.getElementById('violations-list');
    if (!violationsList) return;

    console.log('Displaying results:', results.violations.length + ' violations found');

    if (!results.violations || results.violations.length === 0) {
      violationsList.innerHTML = '<div class="no-violations"><p>No accessibility violations found!</p></div>';
      return;
    }

    // Sort violations by impact (critical -> serious -> moderate -> minor)
    const impactOrder = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    const sortedViolations = results.violations.sort((a, b) => {
      const aImpact = a.impact || 'minor';
      const bImpact = b.impact || 'minor';
      const aOrder = impactOrder[aImpact] !== undefined ? impactOrder[aImpact] : 999;
      const bOrder = impactOrder[bImpact] !== undefined ? impactOrder[bImpact] : 999;
      return aOrder - bOrder;
    });

    let html = '<div class="violations-summary">Found ' + results.violations.length + ' violations:</div>';

    sortedViolations.forEach(function (violation, index) {
      const impact = violation.impact || 'minor';
      const icon = getViolationIcon(impact);
      const nodeCount = violation.nodes ? violation.nodes.length : 0;
      const description = violation.description || violation.help || 'No description available';
      const violationId = 'violation-' + index;
      
      // Generate learn more links
      const learnMoreLinks = generateLearnMoreLinks(violation);

      html += `
        <div class="violation-item ${impact}" id="${violationId}">
          <div class="violation-icon">${icon}</div>
          <div class="violation-content">
            <div class="violation-title">${escapeHtml(violation.id)}</div>
            <div class="violation-description">
              ${escapeHtml(description)}
            </div>
            <div class="violation-actions">
              ${nodeCount > 1 ? `<button class="violation-count" onclick="highlightViolationInstances('${violationId}', ${JSON.stringify(violation.nodes).replace(/"/g, '&quot;')})" title="Click to highlight ${nodeCount} instances on the page">${nodeCount} instances</button>` : ''}
              ${learnMoreLinks}
            </div>
          </div>
        </div>
      `;
    });

    violationsList.innerHTML = html;
  }

  // Add this new function to highlight violation instances
  function highlightViolationInstances(violationId, nodes) {
    console.log('Highlighting instances for:', violationId);

    // Remove any existing highlights
    clearHighlights();

    if (!nodes || !Array.isArray(nodes)) {
      console.warn('No nodes provided for highlighting');
      return;
    }

    let highlightedCount = 0;

    nodes.forEach(function(node, index) {
      if (node.target && Array.isArray(node.target)) {
        // node.target is an array of selectors
        node.target.forEach(function(selector) {
          try {
            const elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
              if (element && !element.closest('#violations-popup')) {
                highlightElement(element, index);
                highlightedCount++;
              }
            });
          } catch (e) {
            console.warn('Invalid selector:', selector, e);
          }
        });
      }
    });

    if (highlightedCount > 0) {
      // Show notification
      showHighlightNotification(highlightedCount);

      // Highlights will persist until manually cleared
      // No auto-clear timeout
    } else {
      console.warn('No elements found to highlight');
    }
  }

  function highlightElement(element, index) {
    // Create highlight overlay
    const highlight = document.createElement('div');
    highlight.className = 'accessibility-highlight';
    highlight.style.cssText = `
      position: absolute;
      background: rgba(255, 0, 0, 0.3);
      border: 2px solid #ff0000;
      pointer-events: none;
      z-index: 9998;
      border-radius: 4px;
      box-shadow: 0 0 0 2px rgba(255, 0, 0, 0.5);
      animation: highlightPulse 2s ease-in-out infinite;
    `;

    // Position the highlight
    const rect = element.getBoundingClientRect();
    highlight.style.top = (rect.top + window.scrollY) + 'px';
    highlight.style.left = (rect.left + window.scrollX) + 'px';
    highlight.style.width = rect.width + 'px';
    highlight.style.height = rect.height + 'px';

    // Add number badge
    const badge = document.createElement('div');
    badge.textContent = index + 1;
    badge.style.cssText = `
      position: absolute;
      top: -8px;
      left: -8px;
      background: #ff0000;
      color: white;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
    `;
    highlight.appendChild(badge);

    document.body.appendChild(highlight);

    // Scroll to first element if it's the first one
    if (index === 0) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function clearHighlights() {
    const highlights = document.querySelectorAll('.accessibility-highlight');
    highlights.forEach(highlight => highlight.remove());

    const notification = document.querySelector('.highlight-notification');
    if (notification) {
      notification.remove();
    }
  }

  function showHighlightNotification(count) {
    // Remove existing notification
    const existing = document.querySelector('.highlight-notification');
    if (existing) {
      existing.remove();
    }

    const notification = document.createElement('div');
    notification.className = 'highlight-notification';
    notification.innerHTML = `
      <p>Highlighted ${count} violation instance${count > 1 ? 's' : ''} on the page</p>
      <button onclick="clearHighlights()" class="clear-highlights-btn">Clear Highlights</button>
    `;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: #0073aa;
      color: white;
      padding: 12px 20px;
      border-radius: 6px;
      z-index: 10000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 14px;
      animation: slideDown 0.3s ease-out;
    `;

    document.body.appendChild(notification);
  }

  function getViolationIcon(impact) {
    const icons = {
      critical: '⚠',
      serious: '⚠',
      moderate: '⚠',
      minor: 'ⓘ'
    };
    return icons[impact] || 'ⓘ';
  }

  function generateLearnMoreLinks(violation) {
    let links = '<div class="learn-more-links">';
    
    // Only show Axe documentation link, renamed to "Learn More..."
    if (violation.helpUrl) {
      links += `<a href="${violation.helpUrl}" target="_blank" rel="noopener" class="learn-more-link" title="Learn more about this accessibility rule">
        Learn More...
      </a>`;
    }
    
    links += '</div>';
    return links;
  }

  function showError(message) {
    const violationsList = document.getElementById('violations-list');
    if (violationsList) {
      violationsList.innerHTML = '<div class="scan-error">❌ ' + escapeHtml(message || 'Error scanning page. Please try again.') + '</div>';
    }
  }

  function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Make functions globally available for onclick handlers
  window.highlightViolationInstances = highlightViolationInstances;
  window.clearHighlights = clearHighlights;

})(jQuery, Drupal, drupalSettings, once);
