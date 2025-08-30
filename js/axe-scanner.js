// web/core/modules/custom/accessibility/js/axe-scanner.js

(function ($, Drupal) {
  'use strict';

  // Function to call the local Ollama API with a prompt.
  function callOllamaMistral(prompt) {
    return fetch('http://localhost:11434/api/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        model: 'mistral',
        prompt: prompt,
        stream: false
      })
    })
      .then(response => response.json());
  }

  // Function to save scan results to Drupal database
  function saveScanResults(url, violations) {
    return fetch('/save-axe-report', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        url: url,
        violations: violations,
        timestamp: new Date().toISOString()
      })
    })
      .then(response => response.json());
  }

  // Function to cache scan results using the new caching system
  function cacheScanResults(url, violations) {
    return fetch('/accessibility/cache/scan-results', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        url: url,
        scan_results: {
          violations: violations,
          timestamp: new Date().toISOString()
        }
      })
    })
      .then(response => response.json())
      .catch(error => {
        console.error('Error caching scan results:', error);
        return { success: false, message: error.message };
      });
  }

  // Function to record that a URL has a scan button
  function recordScanButtonUrl(url) {
    return fetch('/accessibility/cache/record-scan-button', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        url: url
      })
    })
      .then(response => response.json())
      .catch(error => {
        console.error('Error recording scan button URL:', error);
        return { success: false, message: error.message };
      });
  }

  Drupal.behaviors.axeScanner = {
    attach: function (context, settings) {
      // Record that this URL has a scan button (if one exists)
      const currentUrl = window.location.href.split('?')[0]; // Remove query parameters
      const buttonSelectors = [
        '#run-axe-scan-sidebar',
        '#run-axe-scan', 
        '#run-axe-scan-btn',
        '.js-axe-scan-trigger',
        '.axe-scan-button'
      ];
      
      // Check if any scan button exists on this page
      let hasScanButton = false;
      for (const selector of buttonSelectors) {
        if (document.querySelector(selector)) {
          hasScanButton = true;
          break;
        }
      }
      
      // Record this URL if it has a scan button
      if (hasScanButton) {
        recordScanButtonUrl(currentUrl)
          .then(response => {
            console.log('Scan button URL recorded:', response);
          })
          .catch(error => {
            console.error('Failed to record scan button URL:', error);
          });
      }

      // Check if auto_scan parameter is present in URL and automatically trigger scan
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('auto_scan') === '1') {
        // Wait a moment for the page to fully load, then trigger scan
        setTimeout(function() {
          // Try different button selectors in order of preference
          const buttonSelectors = [
            '#run-axe-scan-sidebar',
            '#run-axe-scan', 
            '#run-axe-scan-btn',
            '.js-axe-scan-trigger',
            '.axe-scan-button'
          ];
          
          let scanButton = null;
          for (const selector of buttonSelectors) {
            scanButton = document.querySelector(selector);
            if (scanButton) {
              console.log('Auto-scan: Found scan button with selector:', selector);
              break;
            }
          }
          
          if (scanButton) {
            // Trigger click on the found button
            scanButton.click();
            console.log('Auto-scan: Triggered scan button click');
          } else {
            // If no scan button exists, run the scan directly
            console.log('Auto-scan: No scan button found, running scan directly');
            runAxeScan();
          }
        }, 1500); // Increased timeout to ensure page is fully loaded
      }

      // Function to run axe scan
      function runAxeScan() {
        // Get the current page URL
        const currentUrl = window.location.href.split('?')[0]; // Remove query parameters

        // Run axe-core scan (assumes axe-core is loaded)
        if (typeof axe !== 'undefined') {
          axe.run(document, {}, function (err, results) {
            if (err) {
              console.error('Axe error:', err);
              return;
            }

            const violations = results.violations;
            console.log('Accessibility violations:', violations);

            // Save the scan results to the database (existing system)
            saveScanResults(currentUrl, violations)
              .then(response => {
                console.log('Scan results saved to database:', response);
              })
              .catch(err => {
                console.error('Failed to save scan results to database:', err);
              });

            // Cache the scan results using new caching system
            cacheScanResults(currentUrl, violations)
              .then(cacheResponse => {
                console.log('Scan results cached:', cacheResponse);
                
                if (cacheResponse.success) {
                  // Show a user message with cached violation count
                  const violationsCount = cacheResponse.cached_data && cacheResponse.cached_data.violation_counts 
                    ? cacheResponse.cached_data.violation_counts.total 
                    : violations.length;
                  Drupal.messenger().add('message', `Accessibility scan completed and cached. Found ${violationsCount} violations.`);
                  
                  // Show a popup or notification about scan completion
                  showScanCompletionNotification(violationsCount, currentUrl);
                  
                  // Log cached data for debugging
                  console.log('Cached violation counts:', cacheResponse.cached_data ? cacheResponse.cached_data.violation_counts : 'No counts available');
                } else {
                  Drupal.messenger().add('error', 'Failed to cache scan results: ' + cacheResponse.message);
                }
              })
              .catch(err => {
                console.error('Failed to cache scan results:', err);
                Drupal.messenger().add('error', 'Failed to cache scan results.');
              });

            // Send violations to Ollama/Mistral for summarization
            const prompt = 'Summarize these accessibility violations: ' + JSON.stringify(violations);
            callOllamaMistral(prompt)
              .then(data => {
                console.log('Ollama/Mistral response:', data.response);
                // Optionally display the response in the UI
                const resultsWrapper = document.getElementById('llm-results-wrapper');
                if (resultsWrapper) {
                  resultsWrapper.textContent = data.response;
                }
              })
              .catch(err => {
                console.error('Ollama API error:', err);
              });
          });
        } else {
          console.error('axe-core library not loaded');
          Drupal.messenger().add('error', 'Accessibility scanner not available.');
        }
      }

      // Function to show scan completion notification
      function showScanCompletionNotification(violationsCount, url) {
        const notification = document.createElement('div');
        notification.className = 'accessibility-scan-notification';
        notification.innerHTML = `
          <div class="scan-notification-content">
            <div class="notification-icon">
              ${violationsCount > 0 ? '⚠️' : '✅'}
            </div>
            <div class="notification-text">
              <h3>Accessibility Scan Complete!</h3>
              <p>Found <strong>${violationsCount}</strong> ${violationsCount === 1 ? 'violation' : 'violations'} on this page.</p>
            </div>
            <div class="notification-actions">
              <button onclick="window.open('/admin/config/accessibility/report', '_blank')" class="btn btn-primary">View Full Report</button>
              <button onclick="this.parentElement.parentElement.parentElement.remove()" class="btn btn-secondary">Close</button>
            </div>
          </div>
        `;
        
        // Style the notification
        notification.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: white;
          border: 2px solid ${violationsCount > 0 ? '#dc3545' : '#28a745'};
          border-radius: 12px;
          padding: 20px;
          box-shadow: 0 8px 24px rgba(0,0,0,0.15);
          z-index: 10000;
          max-width: 350px;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        // Add CSS for the content
        const style = document.createElement('style');
        style.textContent = `
          .scan-notification-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
          }
          .notification-icon {
            font-size: 24px;
            text-align: center;
          }
          .notification-text h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #333;
          }
          .notification-text p {
            margin: 0;
            font-size: 14px;
            color: #666;
          }
          .notification-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
          }
          .notification-actions .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
          }
          .notification-actions .btn-primary {
            background: #2c5282;
            color: white;
          }
          .notification-actions .btn-primary:hover {
            background: #2a4a7c;
          }
          .notification-actions .btn-secondary {
            background: #6c757d;
            color: white;
          }
          .notification-actions .btn-secondary:hover {
            background: #5a6268;
          }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Add entrance animation
        setTimeout(() => {
          notification.style.transform = 'translateX(0)';
          notification.style.opacity = '1';
        }, 100);
        
        // Auto-remove after 15 seconds
        setTimeout(() => {
          if (notification.parentElement) {
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
          }
        }, 15000);
      }

      // Example: Run axe scan on a button click
      $('#run-axe-scan', context).on('click', function (e) {
        e.preventDefault();
        runAxeScan();
      });
    }
  };

})(jQuery, Drupal);
