/**
 * @file
 * Axe scan sidebar block functionality.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Behavior for the Axe Scan sidebar block.
   */
  Drupal.behaviors.axeScanSidebar = {
    attach: function (context, settings) {
      // Initialize the Axe scan button in the sidebar.
      once('axe-scan-sidebar-init', '.js-axe-scan-trigger', context).forEach(function(element) {
        const $button = $(element);
        const $resultsContainer = $('#axe-scan-sidebar-results');
        
        $button.on('click', function(e) {
          e.preventDefault();
          
          // Disable button and show loading state
          $button.prop('disabled', true)
                 .addClass('is-loading')
                 .text(Drupal.t('Scanning...'));
          
          // Clear previous results
          $resultsContainer.empty().hide();
          
          // Load axe-core if not already loaded
          if (typeof axe === 'undefined') {
            // Inject axe-core script
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/axe-core@4.7.2/axe.min.js';
            script.onload = function() {
              runAxeScan();
            };
            script.onerror = function() {
              handleScanError('Failed to load Axe scanner library');
            };
            document.head.appendChild(script);
          } else {
            runAxeScan();
          }
        });
        
        /**
         * Run the actual Axe scan.
         */
        function runAxeScan() {
          // Configure axe
          axe.configure({
            resultTypes: ['violations', 'incomplete'],
            runOnly: {
              type: 'tag',
              values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice']
            }
          });
          
          // Run the scan
          axe.run(document, function(err, results) {
            if (err) {
              handleScanError(err);
              return;
            }
            
            displayResults(results);
            
            // Re-enable button
            $button.prop('disabled', false)
                   .removeClass('is-loading')
                   .text(Drupal.t('Run Axe Scan'));
          });
        }
        
        /**
         * Display scan results in the sidebar.
         */
        function displayResults(results) {
          let html = '<div class="axe-scan-summary">';
          
          // Summary header
          html += '<h3>' + Drupal.t('Accessibility Scan Results') + '</h3>';
          
          const violationsCount = results.violations.length;
          const incompleteCount = results.incomplete.length;
          const passesCount = results.passes ? results.passes.length : 0;
          
          // Status summary
          html += '<div class="scan-status-summary">';
          
          if (violationsCount === 0 && incompleteCount === 0) {
            html += '<p class="scan-status scan-status--success">';
            html += '<span class="scan-status-icon">✓</span> ';
            html += Drupal.t('No accessibility violations found!');
            html += '</p>';
          } else {
            html += '<p class="scan-status scan-status--warning">';
            html += '<span class="scan-status-icon">⚠</span> ';
            html += Drupal.t('Found @violations violations and @incomplete incomplete checks', {
              '@violations': violationsCount,
              '@incomplete': incompleteCount
            });
            html += '</p>';
          }
          
          html += '</div>'; // .scan-status-summary
          
          // Statistics
          html += '<div class="scan-statistics">';
          html += '<div class="stat-item stat-violations">';
          html += '<span class="stat-number">' + violationsCount + '</span>';
          html += '<span class="stat-label">' + Drupal.t('Violations') + '</span>';
          html += '</div>';
          html += '<div class="stat-item stat-incomplete">';
          html += '<span class="stat-number">' + incompleteCount + '</span>';
          html += '<span class="stat-label">' + Drupal.t('Needs Review') + '</span>';
          html += '</div>';
          html += '<div class="stat-item stat-passes">';
          html += '<span class="stat-number">' + passesCount + '</span>';
          html += '<span class="stat-label">' + Drupal.t('Passed') + '</span>';
          html += '</div>';
          html += '</div>'; // .scan-statistics
          
          // Violations list (compact for sidebar)
          if (violationsCount > 0) {
            html += '<div class="violations-list-compact">';
            html += '<h4>' + Drupal.t('Violations:') + '</h4>';
            html += '<ul class="violations-summary">';
            
            results.violations.slice(0, 5).forEach(function(violation) {
              html += '<li class="violation-item-compact">';
              html += '<span class="violation-impact violation-impact--' + violation.impact + '">';
              html += violation.impact.toUpperCase() + '</span> ';
              html += '<span class="violation-desc">' + violation.description + '</span>';
              html += ' <span class="violation-count">(' + violation.nodes.length + ' ' + Drupal.t('instances') + ')</span>';
              html += '</li>';
            });
            
            if (violationsCount > 5) {
              html += '<li class="more-violations">' + Drupal.t('...and @count more', {'@count': violationsCount - 5}) + '</li>';
            }
            
            html += '</ul>';
            html += '</div>';
          }
          
          // Link to full report
          html += '<div class="scan-actions">';
          html += '<a href="/accessibility/report" class="button button--small" target="_blank">';
          html += Drupal.t('View Full Report') + '</a>';
          html += '</div>';
          
          html += '</div>'; // .axe-scan-summary
          
          $resultsContainer.html(html).slideDown();
          
          // Store results in drupalSettings for potential use elsewhere
          drupalSettings.accessibility = drupalSettings.accessibility || {};
          drupalSettings.accessibility.lastScanResults = results;
          
          // Trigger custom event
          $(document).trigger('axeScanComplete', [results]);
        }
        
        /**
         * Handle scan errors.
         */
        function handleScanError(error) {
          console.error('Axe scan error:', error);
          
          const errorHtml = '<div class="messages messages--error">' +
                           '<h2>' + Drupal.t('Scan Error') + '</h2>' +
                           '<p>' + Drupal.t('An error occurred while scanning: @error', {'@error': error}) + '</p>' +
                           '</div>';
          
          $resultsContainer.html(errorHtml).show();
          
          // Re-enable button
          $button.prop('disabled', false)
                 .removeClass('is-loading')
                 .text(Drupal.t('Run Axe Scan'));
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
