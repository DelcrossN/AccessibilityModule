(function (Drupal, $) {
  'use strict';

  let isScanning = false;
  let currentScan = null;
  let axeLoaded = false;

  Drupal.behaviors.axePopupOverride = {
    attach: function (context, settings) {
      // Find and override axe scan buttons
      setTimeout(function() {
        findAndOverrideAxeButtons();
      }, 500);
    }
  };

  function findAndOverrideAxeButtons() {
    // Try multiple selectors to find the axe scan button
    var possibleSelectors = [
      '*:contains("Run axe scan")',
      '*:contains("Axe scan")',
      '*:contains("axe")',
      '[data-drupal-selector*="axe"]',
      'button[value*="axe" i]',
      'input[value*="axe" i]',
      'a[href*="axe"]'
    ];

    possibleSelectors.forEach(function(selector) {
      try {
        $(selector).each(function() {
          var $element = $(this);
          var text = $element.text().toLowerCase();

          if (text.includes('axe') || text.includes('scan')) {
            console.log('Found axe button:', this);

            $element
              .off('.axe-popup')
              .on('click.axe-popup', function(e) {
                console.log('Axe button clicked - intercepting');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                if (!isScanning) {
                  showPopupAndScan();
                }
                return false;
              });
          }
        });
      } catch (e) {
        // Ignore selector errors
      }
    });
  }

  function showPopupAndScan() {
    createPopup();

    // Show popup
    const popup = document.getElementById('axe-violations-popup');
    if (popup) {
      popup.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    // Update scanning state
    isScanning = true;

    // Start scanning with a small delay to ensure popup is fully rendered
    setTimeout(() => {
      runAxeScan();
    }, 300);
  }

  function createPopup() {
    // Remove any existing popup
    $('#axe-violations-popup').remove();

    // Create popup HTML matching the reference design
    const popupHtml = `
      <div id="axe-violations-popup" class="popup-overlay">
        <div class="popup-content">
          <div class="popup-header">
            <h3>Accessibility Scan Results</h3>
            <button id="close-axe-popup" type="button" aria-label="Close accessibility results">
              ×
            </button>
          </div>
          <div class="popup-body">
            <div id="axe-violations-list">
              <!-- Results will be inserted here -->
            </div>
          </div>
        </div>
      </div>
    `;

    // Append to body
    $('body').append(popupHtml);

    // Bind close events
    $('#close-axe-popup').on('click', hidePopup);
    $('#axe-violations-popup').on('click', function(e) {
      if (e.target === this) {
        hidePopup();
      }
    });

    // Close on Escape
    $(document).on('keydown.axe-popup', function(e) {
      if (e.key === 'Escape' && $('#axe-violations-popup').hasClass('active')) {
        hidePopup();
      }
    });
  }

  function hidePopup() {
    const popup = document.getElementById('axe-violations-popup');

    if (popup) {
      popup.classList.remove('active');
      document.body.style.overflow = '';
    }

    // Force reset state and cancel any ongoing scan
    if (isScanning) {
      forceResetState();
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
  }

  async function runAxeScan() {
    const violationsList = document.getElementById('axe-violations-list');

    if (!violationsList) {
      resetScanning();
      return;
    }

    try {
      // Show scanning message
      violationsList.innerHTML = '<div class="scanning-message"><p><span class="scan-icon">⟳</span> Scanning page for accessibility violations...</p></div>';

      // Force reset any existing axe state
      if (typeof window.axe !== 'undefined') {
        try {
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
      resetScanning();
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
        exclude: [['#axe-violations-popup']],
        timeout: 30000
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
      resetScanning();

    } catch (error) {
      console.error('Axe scan error:', error);

      let errorMessage = 'Error during accessibility scan';

      if (error.message && error.message.includes('already running')) {
        errorMessage = 'Scanner is busy. Resetting and trying again...';
        forceResetState();
        setTimeout(() => {
          if (!isScanning) {
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
      resetScanning();
    }
  }

  function resetScanning() {
    isScanning = false;
    currentScan = null;
  }

  function displayResults(results) {
    const violationsList = document.getElementById('axe-violations-list');
    if (!violationsList) return;

    console.log('Displaying results:', results.violations.length + ' violations found');

    if (!results.violations || results.violations.length === 0) {
      violationsList.innerHTML = '<div class="no-violations"><p>✅ No accessibility violations found!</p></div>';
      return;
    }

    // Sort violations by impact
    const impactOrder = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    const sortedViolations = results.violations.sort((a, b) => {
      return (impactOrder[a.impact] || 999) - (impactOrder[b.impact] || 999);
    });

    let html = '<div class="violations-summary">Found ' + results.violations.length + ' violations:</div>';

    sortedViolations.forEach(function (violation, index) {
      const impact = violation.impact || 'minor';
      const icon = getViolationIcon(impact);
      const nodeCount = violation.nodes ? violation.nodes.length : 0;
      const description = violation.description || violation.help || 'No description available';
      const violationId = 'violation-' + index;

      html += `
        <div class="violation-item ${impact}" id="${violationId}">
          <div class="violation-icon">${icon}</div>
          <div class="violation-content">
            <div class="violation-title">${escapeHtml(violation.id)}</div>
            <div class="violation-description">
              ${escapeHtml(description)}
            </div>
            ${nodeCount > 1 ? `<button class="violation-count" onclick="highlightViolationInstances('${violationId}', ${JSON.stringify(violation.nodes).replace(/"/g, '&quot;')})" title="Click to highlight ${nodeCount} instances on the page">${nodeCount} instances</button>` : ''}
          </div>
        </div>
      `;
    });

    violationsList.innerHTML = html;
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

  function showError(message) {
    const violationsList = document.getElementById('axe-violations-list');
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

  // Highlight functions (matching reference design)
  window.highlightViolationInstances = function(violationId, nodes) {
    console.log('Highlighting instances for:', violationId);

    clearHighlights();

    if (!nodes || !Array.isArray(nodes)) {
      console.warn('No nodes provided for highlighting');
      return;
    }

    let highlightedCount = 0;

    nodes.forEach(function(node, index) {
      if (node.target && Array.isArray(node.target)) {
        node.target.forEach(function(selector) {
          try {
            const elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
              if (element && !element.closest('#axe-violations-popup')) {
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
      showHighlightNotification(highlightedCount);
      setTimeout(clearHighlights, 10000);
    } else {
      console.warn('No elements found to highlight');
    }
  };

  function highlightElement(element, index) {
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

    const rect = element.getBoundingClientRect();
    highlight.style.top = (rect.top + window.scrollY) + 'px';
    highlight.style.left = (rect.left + window.scrollX) + 'px';
    highlight.style.width = rect.width + 'px';
    highlight.style.height = rect.height + 'px';

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
    const existing = document.querySelector('.highlight-notification');
    if (existing) {
      existing.remove();
    }

    const notification = document.createElement('div');
    notification.className = 'highlight-notification';
    notification.innerHTML = `
      <p> Highlighted ${count} violation instance${count > 1 ? 's' : ''} on the page</p>
      <button onclick="clearHighlights()" class="clear-highlights-btn">Clear Highlights</button>
    `;

    document.body.appendChild(notification);
  }

  window.clearHighlights = clearHighlights;

})(Drupal, jQuery);

