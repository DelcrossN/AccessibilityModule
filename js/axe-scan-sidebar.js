/**
 * @file
 * Axe scan sidebar block functionality with sliding popup.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  let isScanning = false;
  let currentScan = null;
  let axeLoaded = false;
  
  // Cache-related variables
  let scanCache = new Map();
  let currentPageHash = null;

  /**
   * Generate a simple hash of page content to detect changes.
   */
  function generatePageHash() {
    const htmlContent = document.documentElement.innerHTML;
    const stylesheets = Array.from(document.styleSheets).map(sheet => {
      try {
        // Try to access stylesheet rules (may fail for cross-origin stylesheets)
        return Array.from(sheet.cssRules || sheet.rules || []).map(rule => rule.cssText).join('');
      } catch (e) {
        // Fallback for cross-origin stylesheets - use href
        return sheet.href || '';
      }
    }).join('');
    
    const content = htmlContent + stylesheets;
    let hash = 0;
    for (let i = 0; i < content.length; i++) {
      const char = content.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return hash.toString();
  }

  /**
   * Get cached scan results if available and page hasn't changed.
   */
  function getCachedResults() {
    const pageUrl = window.location.href;
    const currentHash = generatePageHash();
    
    // Check if we have cached results for this URL
    const cacheEntry = scanCache.get(pageUrl);
    if (cacheEntry && cacheEntry.hash === currentHash) {
      console.log('Using cached accessibility scan results');
      return cacheEntry.results;
    }
    
    console.log('Page content changed or no cache found, will perform new scan');
    return null;
  }

  /**
   * Cache scan results with current page hash.
   */
  function cacheResults(results) {
    const pageUrl = window.location.href;
    const currentHash = generatePageHash();
    
    scanCache.set(pageUrl, {
      hash: currentHash,
      results: results,
      timestamp: Date.now()
    });
    
    console.log('Accessibility scan results cached for:', pageUrl);
    
    // Save results to server for reports
    saveResultsToServer(results);
    
    // Optional: Clean up old cache entries (keep last 10 pages)
    if (scanCache.size > 10) {
      const oldestKey = scanCache.keys().next().value;
      scanCache.delete(oldestKey);
    }
  }

  /**
   * Save scan results to server for reports system.
   */
  function saveResultsToServer(results) {
    const reportData = {
      url: window.location.href,
      title: document.title,
      violations: results.violations,
      timestamp: Date.now(),
      user_agent: navigator.userAgent
    };

    console.log('Sending scan results to server:', reportData);

    // Send to server endpoint
    fetch('/save-axe-report', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(reportData)
    })
    .then(response => {
      console.log('Server response status:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Scan results saved to server successfully:', data);
      if (data.success) {
        console.log('Report saved successfully. Summary:', data.summary);
      } else {
        console.error('Server returned error:', data.error);
      }
    })
    .catch(error => {
      console.error('Error saving scan results to server:', error);
      // Still continue with the UI display even if server save fails
    });
  }

  /**
   * Clear all cached scan results.
   */
  function clearScanCache() {
    scanCache.clear();
    console.log('All accessibility scan cache cleared');
  }

  /**
   * Clear cache for specific URL.
   */
  function clearCacheForUrl(url) {
    scanCache.delete(url);
    console.log('Cache cleared for URL:', url);
  }

  /**
   * Check for auto-scan parameter and automatically trigger the scan popup.
   */
  function checkForAutoScanParameter() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_scan') === 'true') {
      // Wait a moment for the page to fully load, then trigger scan
      setTimeout(() => {
        showPopupAndScan();
        
        // Clean URL parameter after triggering scan
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('auto_scan');
        window.history.replaceState({}, document.title, newUrl.toString());
      }, 1000); // 1 second delay to ensure page is ready
    }
  }

  /**
   * Behavior for the Axe Scan sidebar block.
   */
  Drupal.behaviors.axeScanSidebar = {
    attach: function (context, settings) {
      // Check for auto-scan parameter on page load
      checkForAutoScanParameter();
      
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
    // Check for cached results first, before creating popup
    const cachedResults = getCachedResults();
    const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');
    
    if (cachedResults) {
      // Create popup and show cached results immediately
      createPopup();
      const popup = document.getElementById('violations-popup');
      if (!popup) return;
      
      // Show popup
      popup.classList.add('active');
      document.body.style.overflow = 'hidden';
      
      // Display cached results immediately (no delay)
      displayResults(cachedResults, true);
      updateButtonState(button, false);
      isScanning = false;
    } else {
      // Create popup for fresh scan
      createPopup();
      const popup = document.getElementById('violations-popup');
      if (!popup) return;

      // Show popup
      popup.classList.add('active');
      document.body.style.overflow = 'hidden';
      
      // Hide footer immediately when starting fresh scan
      const footer = document.querySelector('.popup-footer');
      if (footer) {
        footer.classList.remove('show');
      }

      // Update button and scanning state
      isScanning = true;
      updateButtonState(button, true);

      // Start scanning with a small delay to ensure popup is fully rendered
      setTimeout(() => {
        runAxeScan();
      }, 300);
    }
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
          <div class="popup-footer">
            <a href="https://my-drupal10-site.ddev.site/admin/config/accessibility/report" 
               class="full-report-button" 
               target="_blank" 
               rel="noopener">
              See Full Report
            </a>
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

      // Hide footer during scanning
      const footer = document.querySelector('.popup-footer');
      if (footer) {
        footer.classList.remove('show');
      }

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
      
      // Cache the results for future use
      cacheResults(results);
      
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

  function displayResults(results, fromCache = false) {
    const violationsList = document.getElementById('violations-list');
    if (!violationsList) return;

    console.log('Displaying results:', results.violations.length + ' violations found');

    if (!results.violations || results.violations.length === 0) {
      const cacheIndicator = fromCache ? ' <span style="font-size: 0.85em; opacity: 0.7;">(cached)</span>' : '';
      violationsList.innerHTML = '<div class="no-violations"><p>No accessibility violations found!' + cacheIndicator + '</p></div>';
      
      // Show the footer with "See Full Report" button even when no violations found
      const footer = document.querySelector('.popup-footer');
      if (footer) {
        footer.classList.add('show');
      }
      
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

    const cacheIndicator = fromCache ? ' <span style="font-size: 0.85em; opacity: 0.7; font-weight: normal;">(cached) <a href="#" id="force-rescan" style="color: #2c5282; text-decoration: underline; font-size: 0.9em;">Force Rescan</a></span>' : '';
    let html = '<div class="violations-summary">Found ' + results.violations.length + ' violations:' + cacheIndicator + '</div>';

    sortedViolations.forEach(function (violation, index) {
      const impact = violation.impact || 'minor';
      const icon = getViolationIcon(impact);
      const nodeCount = violation.nodes ? violation.nodes.length : 0;
      const description = violation.description || violation.help || 'No description available';
      const violationId = 'violation-' + index;
      
      // Generate learn more links
      const learnMoreLinks = generateLearnMoreLinks(violation);
      const statusText = getViolationStatusText(impact);

      // Check if chatbot is enabled and user opted in
      const chatbotEnabled = window.drupalSettings && 
                            window.drupalSettings.accessibility && 
                            window.drupalSettings.accessibility.axeScanBlock && 
                            window.drupalSettings.accessibility.axeScanBlock.chatbot &&
                            window.drupalSettings.accessibility.axeScanBlock.chatbot.enabled;
      
      const chatbotOptedIn = document.getElementById('enable-chatbot-help') && 
                           document.getElementById('enable-chatbot-help').checked;

      let chatbotInterface = '';
      if (chatbotEnabled && chatbotOptedIn) {
        const chatbotContainerId = `chatbot-${violationId}`;
        chatbotInterface = `
          <div class="chatbot-interface" id="${chatbotContainerId}">
            <div class="chatbot-header">
              <button type="button" class="chatbot-toggle" onclick="toggleChatbot('${chatbotContainerId}')">
                🤖 Ask AI for Help
              </button>
            </div>
            <div class="chatbot-content" style="display: none;">
              <div class="chatbot-input-section">
                <textarea 
                  placeholder="Ask a specific question about fixing this violation..." 
                  class="chatbot-question-input"
                  rows="2"
                  id="${chatbotContainerId}-input"
                ></textarea>
                <button 
                  type="button" 
                  class="chatbot-submit-btn"
                  onclick="submitChatbotQuestion('${chatbotContainerId}', '${escapeHtml(violation.id)}', '${escapeHtml(description).replace(/'/g, "\\'")}')">
                  Get AI Solution
                </button>
              </div>
              <div class="chatbot-response"></div>
            </div>
          </div>
        `;
      }

      html += `
        <div class="violation-item ${impact}" id="${violationId}">
          <div class="violation-icon">${icon}</div>
          <div class="violation-content">
            <div class="violation-title-row">
              <div class="violation-title">${escapeHtml(violation.id)}</div>
              <div class="violation-status">${statusText}</div>
            </div>
            <div class="violation-description">
              ${escapeHtml(description)}
            </div>
            <div class="violation-actions">
              ${nodeCount > 1 ? `<button class="violation-count" onclick="highlightViolationInstances('${violationId}', ${JSON.stringify(violation.nodes).replace(/"/g, '&quot;')})" title="Click to highlight ${nodeCount} instances on the page">${nodeCount} instances</button>` : ''}
              ${learnMoreLinks}
            </div>
            ${chatbotInterface}
          </div>
        </div>
      `;
    });

    violationsList.innerHTML = html;
    
    // Show the footer with "See Full Report" button after displaying results
    const footer = document.querySelector('.popup-footer');
    if (footer) {
      footer.classList.add('show');
    }
    
    // Add event listener for force rescan link if it exists
    if (fromCache) {
      const forceRescanLink = document.getElementById('force-rescan');
      if (forceRescanLink) {
        forceRescanLink.addEventListener('click', function(e) {
          e.preventDefault();
          // Clear cache for current page and perform fresh scan
          const pageUrl = window.location.href;
          scanCache.delete(pageUrl);
          console.log('Cache cleared, performing fresh scan...');
          
          // Update button state and start new scan
          const button = document.getElementById('run-axe-scan-sidebar') || document.querySelector('.js-axe-scan-trigger');
          isScanning = true;
          updateButtonState(button, true);
          
          // Start scanning
          setTimeout(() => {
            runAxeScan();
          }, 100);
        });
      }
    }
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

  function getViolationStatusText(impact) {
    switch (impact) {
      case 'critical':
        return 'Status: CRITICAL';
      case 'serious':
        return 'Status: SEVERE';
      case 'moderate':
        return 'Status: MODERATE';
      case 'minor':
        return 'Status: MINOR';
      default:
        return 'Status: UNKNOWN';
    }
  }

  function generateLearnMoreLinks(violation) {
    let links = '<div class="learn-more-links">';
    
    // Only show Axe documentation link, renamed to "Learn More..."
    if (violation.helpUrl) {
      links += `<a href="${violation.helpUrl}" target="_blank" rel="noopener" class="learn-more-link" title="Learn more about this accessibility rule">
        Learn More ↗
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
    
    // Hide footer during error state
    const footer = document.querySelector('.popup-footer');
    if (footer) {
      footer.classList.remove('show');
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
  
  // Make cache functions available for debugging
  window.clearAxeScanCache = clearScanCache;
  window.clearAxeCacheForUrl = clearCacheForUrl;

  // Chatbot functionality
  window.toggleChatbot = function(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const content = container.querySelector('.chatbot-content');
    const toggle = container.querySelector('.chatbot-toggle');
    
    if (content.style.display === 'none') {
      content.style.display = 'block';
      toggle.textContent = '🤖 Hide AI Help';
    } else {
      content.style.display = 'none';
      toggle.textContent = '🤖 Ask AI for Help';
    }
  };

  /**
   * Enhanced markdown renderer for chatbot responses
   */
  function renderMarkdownToHtml(markdown) {
    if (!markdown) return '';
    
    let html = markdown;
    
    // Convert code blocks (```language ... ```)
    html = html.replace(/```(\w+)?\n?([\s\S]*?)```/g, function(match, language, code) {
      language = language || '';
      const langClass = language ? ` class="language-${language}"` : '';
      const langLabel = language ? `<span class="code-lang">${language}</span>` : '';
      return `<div class="code-block-wrapper">
        ${langLabel}
        <pre><code${langClass}>${escapeHtml(code.trim())}</code></pre>
        <button class="copy-code-btn" onclick="copyCodeToClipboard(this)" title="Copy code">📋 Copy</button>
      </div>`;
    });
    
    // Convert inline code (`code`)
    html = html.replace(/`([^`\n]+)`/g, '<code class="inline-code">$1</code>');
    
    // Convert headers (# ## ###)
    html = html.replace(/^### (.*$)/gim, '<h4>$1</h4>');
    html = html.replace(/^## (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^# (.*$)/gim, '<h2>$1</h2>');
    
    // Convert bold (**text**)
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Convert italic (*text*)
    html = html.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    
    // Convert lists (- item or * item)
    html = html.replace(/^[\*\-\+]\s+(.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    // Convert numbered lists
    html = html.replace(/^\d+\.\s+(.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)(?!<li>)/s, '$1</ol>');
    html = html.replace(/(<li>.*<\/li>\n)+/s, function(match) {
      if (match.includes('<ol>')) {
        return match;
      }
      return '<ol>' + match + '</ol>';
    });
    
    // Convert paragraphs (double newlines)
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    
    // Clean up empty paragraphs
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p>\s*<ul>/g, '<ul>');
    html = html.replace(/<\/ul>\s*<\/p>/g, '</ul>');
    html = html.replace(/<p>\s*<ol>/g, '<ol>');
    html = html.replace(/<\/ol>\s*<\/p>/g, '</ol>');
    
    return html;
  }

  /**
   * Copy code block to clipboard
   */
  window.copyCodeToClipboard = function(button) {
    const codeBlock = button.previousElementSibling;
    const code = codeBlock.textContent || codeBlock.innerText;
    
    navigator.clipboard.writeText(code).then(() => {
      const originalText = button.textContent;
      button.textContent = '✅ Copied!';
      button.style.background = '#4CAF50';
      setTimeout(() => {
        button.textContent = originalText;
        button.style.background = '';
      }, 2000);
    }).catch(() => {
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = code;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      
      const originalText = button.textContent;
      button.textContent = '✅ Copied!';
      button.style.background = '#4CAF50';
      setTimeout(() => {
        button.textContent = originalText;
        button.style.background = '';
      }, 2000);
    });
  };

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }

  window.submitChatbotQuestion = function(containerId, violationId, violationDescription) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const input = container.querySelector('.chatbot-question-input');
    const responseDiv = container.querySelector('.chatbot-response');
    const submitBtn = container.querySelector('.chatbot-submit-btn');
    
    const userQuestion = input.value.trim();
    
    // Enhanced loading state
    responseDiv.innerHTML = `
      <div class="loading">
        <div class="loading-spinner"></div>
        <div class="loading-text">Analyzing accessibility issue...</div>
        <div class="loading-subtext">This may take a moment</div>
      </div>
    `;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Analyzing...';
    
    // Prepare the AJAX request
    const formData = new FormData();
    formData.append('violation_id', violationId);
    formData.append('violation_description', violationDescription);
    formData.append('user_question', userQuestion);
    
    // Add timeout to the fetch request
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
    
    fetch('/accessibility/chatbot/ajax', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      signal: controller.signal
    })
    .then(response => {
      clearTimeout(timeoutId);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        const renderedSolution = renderMarkdownToHtml(data.solution);
        const cacheIndicator = data.cached ? ' <span class="cache-indicator">(from cache)</span>' : '';
        
        let solutionHtml = '<div class="chatbot-solution">';
        solutionHtml += '<div class="solution-header">💡 AI Accessibility Solution' + cacheIndicator + '</div>';
        solutionHtml += '<div class="solution-content">' + renderedSolution + '</div>';
        
        if (data.model_used) {
          const cacheNote = data.cached ? ' <small class="cache-note">⚡ Instant response</small>' : '';
          solutionHtml += '<div class="solution-footer">Powered by ' + escapeHtml(data.model_used) + cacheNote + '</div>';
        }
        solutionHtml += '</div>';
        responseDiv.innerHTML = solutionHtml;
        
        // Add syntax highlighting if Prism is available
        if (typeof Prism !== 'undefined') {
          setTimeout(() => {
            responseDiv.querySelectorAll('pre code').forEach((block) => {
              Prism.highlightElement(block);
            });
          }, 100);
        }
      } else {
        responseDiv.innerHTML = `
          <div class="error">
            <div class="error-icon">⚠️</div>
            <div class="error-message">${escapeHtml(data.error || 'Failed to get AI solution')}</div>
            <div class="error-help">Try rephrasing your question or check your internet connection.</div>
          </div>
        `;
      }
    })
    .catch(error => {
      clearTimeout(timeoutId);
      console.error('Chatbot request failed:', error);
      
      let errorMessage = 'Network error. Please try again.';
      let errorHelp = 'Check your internet connection and try again.';
      
      if (error.name === 'AbortError') {
        errorMessage = 'Request timed out.';
        errorHelp = 'The AI service took too long to respond. Please try again.';
      } else if (error.message.includes('HTTP')) {
        errorMessage = 'Server error.';
        errorHelp = 'There was a problem with the AI service. Please try again later.';
      }
      
      responseDiv.innerHTML = `
        <div class="error">
          <div class="error-icon">⚠️</div>
          <div class="error-message">${errorMessage}</div>
          <div class="error-help">${errorHelp}</div>
        </div>
      `;
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Get AI Solution';
    });
  };

})(jQuery, Drupal, drupalSettings, once);
