/**
 * @file
 * JavaScript for the accessibility test violations page with popup scanner.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Initialize test violations page functionality.
   */
  Drupal.behaviors.testViolations = {
    attach: function (context, settings) {
      // Only run once per page load
      $('body', context).once().each(function () {
        initializeProblematicElements();
        addKeyboardTraps();
        createDynamicContent();
        initializeAxeScanner();
      });
    }
  };

  /**
   * Initialize the Axe accessibility scanner popup functionality.
   */
  function initializeAxeScanner() {
    console.log('Initializing Axe Scanner popup...');

    // Bind scan button
    $(document).on('click', '#run-axe-scan-btn', function(e) {
      e.preventDefault();
      console.log('Run scan button clicked');

      if (typeof axe === 'undefined') {
        loadAxeCore(function() {
          runAxeScan();
        });
      } else {
        runAxeScan();
      }
    });

    // Bind popup close button
    $(document).on('click', '#close-axe-popup', function(e) {
      e.preventDefault();
      closeAxePopup();
    });

    // Close popup when clicking overlay
    $(document).on('click', '.axe-popup-overlay', function(e) {
      if (e.target === this) {
        closeAxePopup();
      }
    });

    // Bind rescan button
    $(document).on('click', '#rescan-btn', function(e) {
      e.preventDefault();
      runAxeScan();
    });

    // Bind export button
    $(document).on('click', '#export-results-btn', function(e) {
      e.preventDefault();
      exportResults();
    });

    // Handle escape key
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && $('#axe-scanner-popup').hasClass('active')) {
        closeAxePopup();
      }
    });

    // Add popup HTML to page if it doesn't exist
    if ($('#axe-scanner-popup').length === 0) {
      addPopupHTML();
    }
  }

  /**
   * Add popup HTML to the page.
   */
  function addPopupHTML() {
    const popupHTML = `
      <div id="axe-scanner-popup" class="axe-popup-overlay">
        <div class="axe-popup-content">
          <div class="axe-popup-header">
            <h2>Accessibility Scan Results</h2>
            <button id="close-axe-popup" class="close-btn">&times;</button>
          </div>

          <div class="axe-popup-body">
            <div id="axe-scan-loading" class="loading-state">
              <div class="loading-spinner"></div>
              <p>Scanning page for accessibility violations...</p>
            </div>

            <div id="axe-scan-error" class="error-state" style="display: none;">
              <div class="error-icon">⚠️</div>
              <p id="axe-error-message">An error occurred during scanning.</p>
            </div>

            <div id="axe-scan-results" class="results-state" style="display: none;">
              <div class="axe-summary">
                <h3>Found violations:</h3>
              </div>
              <div id="axe-violations-container" class="violations-container">
              </div>
            </div>
          </div>

          <div class="axe-popup-footer">
            <button id="rescan-btn" class="btn btn-primary">Rescan</button>
            <button id="export-results-btn" class="btn btn-secondary">Export Results</button>
          </div>
        </div>
      </div>
    `;

    $('body').append(popupHTML);
  }

  /**
   * Load the axe-core library from CDN.
   */
  function loadAxeCore(callback) {
    console.log('Loading axe-core from CDN...');

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/axe-core@4.8.2/axe.min.js';
    script.onload = function() {
      console.log('Axe-core loaded successfully');
      if (callback) callback();
    };
    script.onerror = function() {
      console.error('Failed to load axe-core library');
      showAxeError('Failed to load the accessibility scanning library. Please check your internet connection and try again.');
    };
    document.head.appendChild(script);
  }

  /**
   * Run the axe accessibility scan.
   */
  function runAxeScan() {
    console.log('Running Axe scan...');

    // Show popup and loading state
    showAxePopup();
    showLoadingState();

    // Update scan button
    $('#run-axe-scan-btn').addClass('scanning').prop('disabled', true).html('Scanning...');

    // Configure axe
    const axeConfig = {
      rules: {},
      tags: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice']
    };

    // Run axe scan
    axe.run(document, axeConfig, function(err, results) {
      console.log('Axe scan completed');

      // Reset scan button
      $('#run-axe-scan-btn').removeClass('scanning').prop('disabled', false).html('Run Axe Scan');

      if (err) {
        console.error('Axe scan error:', err);
        showAxeError('Error running accessibility scan: ' + err.message);
        return;
      }

      displayResults(results);
    });
  }

  /**
   * Show the axe popup with slide-in animation.
   */
  function showAxePopup() {
    const popup = $('#axe-scanner-popup');
    popup.addClass('active');
    $('body').addClass('popup-open');
  }

  /**
   * Close the axe popup with animation.
   */
  function closeAxePopup() {
    $('#axe-scanner-popup').removeClass('active');
    $('body').removeClass('popup-open');
  }

  /**
   * Show loading state in popup.
   */
  function showLoadingState() {
    $('#axe-scan-loading').show();
    $('#axe-scan-results').hide();
    $('#axe-scan-error').hide();
  }

  /**
   * Display scan results in popup.
   */
  function displayResults(results) {
    $('#axe-scan-loading').hide();
    $('#axe-scan-error').hide();
    $('#axe-scan-results').show();

    // Update summary
    const violationCount = results.violations.length;
    $('.axe-summary h3').text(`Found ${violationCount} violation${violationCount !== 1 ? 's' : ''}:`);

    // Clear previous results
    $('#axe-violations-container').empty();

    if (results.violations.length === 0) {
      $('#axe-violations-container').html('<div class="no-violations">✅ No accessibility violations found!</div>');
      return;
    }

    // Display violations
    results.violations.forEach(function(violation, index) {
      const violationHtml = createViolationHTML(violation, index);
      $('#axe-violations-container').append(violationHtml);
    });

    // Store results for export
    window.axeResults = results;
  }

  /**
   * Create HTML for a single violation.
   */
  function createViolationHTML(violation, index) {
    const impactClass = violation.impact || 'moderate';
    const nodeCount = violation.nodes.length;
    const instanceText = nodeCount === 1 ? '1 instance' : `${nodeCount} instances`;

    const impactIcon = {
      'critical': '🔴',
      'serious': '🟠',
      'moderate': '🟡',
      'minor': '🟢'
    };

    let nodesHtml = '';
    violation.nodes.slice(0, 3).forEach(function(node) {
      const target = Array.isArray(node.target) ? node.target.join(', ') : node.target;
      nodesHtml += `<div class="axe-violation-node">${escapeHtml(target)}</div>`;
    });

    if (violation.nodes.length > 3) {
      nodesHtml += `<div class="axe-violation-node more">... and ${violation.nodes.length - 3} more elements</div>`;
    }

    return `
      <div class="axe-violation-item ${impactClass}" data-violation-id="${index}">
        <div class="axe-violation-header" onclick="toggleViolationDetails(${index})">
          <div class="violation-title-row">
            <span class="impact-icon">${impactIcon[impactClass] || '🔵'}</span>
            <div class="violation-title">${escapeHtml(violation.id)}</div>
            <span class="instance-count">${instanceText}</span>
          </div>
          <div class="violation-description">
            ${escapeHtml(violation.description || violation.help)}
          </div>
        </div>
        <div class="axe-violation-details" id="violation-details-${index}">
          ${violation.help ? `
            <div class="violation-help">
              <strong>How to fix:</strong><br>
              ${escapeHtml(violation.help)}
            </div>
          ` : ''}
          <div class="violation-nodes">
            <strong>Affected elements:</strong>
            ${nodesHtml}
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Toggle violation details.
   */
  window.toggleViolationDetails = function(index) {
    const detailsElement = $(`#violation-details-${index}`);
    const headerElement = $(`.axe-violation-item[data-violation-id="${index}"] .axe-violation-header`);

    if (detailsElement.is(':visible')) {
      detailsElement.slideUp(200);
      headerElement.removeClass('expanded');
    } else {
      detailsElement.slideDown(200);
      headerElement.addClass('expanded');
    }
  };

  /**
   * Show error message in popup.
   */
  function showAxeError(message) {
    $('#axe-scan-loading').hide();
    $('#axe-scan-results').hide();
    $('#axe-scan-error').show();
    $('#axe-error-message').text(message);
    showAxePopup();
  }

  /**
   * Export scan results as JSON.
   */
  function exportResults() {
    if (!window.axeResults) {
      alert('No scan results to export.');
      return;
    }

    const data = JSON.stringify(window.axeResults, null, 2);
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = `axe-scan-results-${new Date().toISOString().slice(0, 19)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    URL.revokeObjectURL(url);
  }

  /**
   * Escape HTML to prevent XSS.
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Keep your existing problematic elements functions...
  function initializeProblematicElements() {
    $('.focus-issues-section div[onclick]').click(function() {
      alert('This div acts like a button but cannot be focused with keyboard');
    });

    $('.dropdown-trigger').click(function() {
      var dropdown = $(this).siblings('.dropdown-content');
      dropdown.toggle();
    });

    $(document).click(function(e) {
      if (!$(e.target).closest('.custom-dropdown').length) {
        $('.dropdown-content').hide();
      }
    });

    $('[role="button"]').click(function() {
      alert('ARIA button clicked but no keyboard support');
    });

    $('[role="tab"]').click(function() {
      $('[role="tab"]').attr('aria-selected', 'false');
      $(this).attr('aria-selected', 'true');
    });

    setTimeout(function() {
      $('input[type="text"]').first().focus();
    }, 1000);
  }

  function addKeyboardTraps() {
    $('.custom-dropdown input, .custom-dropdown select').keydown(function(e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        $(this).focus();
      }
    });
  }

  function createDynamicContent() {
    setTimeout(function() {
      var newContent = $('<div class="dynamic-content" style="background: yellow; padding: 10px; margin: 10px 0;">New content appeared without announcement!</div>');
      $('.violations-container').append(newContent);
    }, 3000);

    setInterval(function() {
      $('.flashing-element').text(function(i, text) {
        return text === 'Flashing text that could trigger seizures'
          ? 'Still flashing and problematic'
          : 'Flashing text that could trigger seizures';
      });
    }, 2000);
  }

  // Global functions for inline event handlers
  window.toggleDropdown = function() {
    $('.dropdown-content').toggle();
  };

  window.selectOption = function(option) {
    alert('Selected: ' + option);
    $('.dropdown-content').hide();
  };

  window.handleClick = function() {
    alert('Div clicked, but not accessible via keyboard');
  };

  $(document).contextmenu(function() {
    return false;
  });

  $('form').submit(function(e) {
    e.preventDefault();
    alert('Form submitted, but no proper validation or error messages');
  });

  setInterval(function() {
    var time = new Date().toLocaleTimeString();
    if ($('.auto-refresh').length === 0) {
      $('<div class="auto-refresh" style="position: fixed; top: 10px; right: 10px; background: red; color: white; padding: 5px;">Auto-updating: ' + time + '</div>').appendTo('body');
    } else {
      $('.auto-refresh').text('Auto-updating: ' + time);
    }
  }, 5000);

})(jQuery, Drupal);
