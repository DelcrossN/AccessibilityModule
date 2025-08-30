/**
 * @file
 * Renders accessibility violations on report pages using popup rendering logic.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.accessibilityReportViolations = {
    attach: function (context, settings) {
      // Only run on report pages
      if (!document.querySelector('#report-violations-list')) {
        return;
      }

      // Get violations data from Drupal settings or data attributes
      const violationsData = drupalSettings.accessibilityReport || {};
      
      console.log('Report violations data:', violationsData);
      
      // Count violations in real-time from JavaScript data
      updateViolationCounts(violationsData.violations || []);
      
      if (violationsData.violations && violationsData.violations.length > 0) {
        displayViolations(violationsData);
      } else {
        // Show no violations message if no data
        const violationsList = document.getElementById('report-violations-list');
        if (violationsList) {
          violationsList.innerHTML = '<div class="no-violations"><p>No accessibility violations found!</p></div>';
        }
      }
    }
  };

  /**
   * Update violation counts in real-time from JavaScript violations data
   * This function is scalable for any number of violation types and pages
   */
  function updateViolationCounts(violations) {
    const counts = {
      total: 0,
      critical: 0,
      serious: 0,
      moderate: 0,
      minor: 0
    };

    // Validate input
    if (!Array.isArray(violations)) {
      console.warn('Violations data is not an array:', violations);
      violations = [];
    }

    // Count violations by impact level with robust error handling
    violations.forEach(function(violation, index) {
      try {
        const impact = violation.impact || 'minor';
        counts.total++;
        
        // Only increment if the impact level exists in our counts object
        if (counts.hasOwnProperty(impact)) {
          counts[impact]++;
        } else {
          console.warn('Unknown violation impact level:', impact, 'at index', index);
          counts.minor++; // Default to minor for unknown impacts
        }
      } catch (error) {
        console.error('Error processing violation at index', index, ':', error);
      }
    });

    console.log('Real-time violation counts:', counts);

    // Update all card numbers with error handling
    const cardMappings = [
      { class: 'total-violations', count: counts.total },
      { class: 'critical-violations', count: counts.critical },
      { class: 'serious-violations', count: counts.serious },
      { class: 'moderate-violations', count: counts.moderate },
      { class: 'minor-violations', count: counts.minor }
    ];

    cardMappings.forEach(function(mapping) {
      updateCardNumber(mapping.class, mapping.count);
    });
  }

  /**
   * Update a specific card's number with error handling
   */
  function updateCardNumber(cardClass, count) {
    try {
      const card = document.querySelector('.stats-card.' + cardClass + ' .card-number');
      if (card) {
        card.textContent = count;
      } else {
        console.warn('Card not found for class:', cardClass);
      }
    } catch (error) {
      console.error('Error updating card number for', cardClass, ':', error);
    }
  }

  function displayViolations(data) {
    const violationsList = document.getElementById('report-violations-list');
    if (!violationsList) return;

    console.log('Displaying violations on report page:', data.violations.length + ' violations found');

    if (!data.violations || data.violations.length === 0) {
      violationsList.innerHTML = '<div class="no-violations"><p>No accessibility violations found!</p></div>';
      return;
    }

    // Sort violations by impact (critical -> serious -> moderate -> minor)
    const impactOrder = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    const sortedViolations = data.violations.sort((a, b) => {
      const aImpact = a.impact || 'minor';
      const bImpact = b.impact || 'minor';
      const aOrder = impactOrder[aImpact] !== undefined ? impactOrder[aImpact] : 999;
      const bOrder = impactOrder[bImpact] !== undefined ? impactOrder[bImpact] : 999;
      return aOrder - bOrder;
    });

    let html = '<div class="violations-summary">Found ' + data.violations.length + ' violations:</div>';

    sortedViolations.forEach(function (violation, index) {
      const impact = violation.impact || 'minor';
      const icon = getViolationIcon(impact);
      const nodeCount = violation.nodes ? violation.nodes.length : 0;
      const description = violation.description || violation.help || 'No description available';
      const violationId = 'violation-' + index;
      
      // Generate learn more links
      const learnMoreLinks = generateLearnMoreLinks(violation);
      const statusText = getViolationStatusText(impact);

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
              ${nodeCount > 1 ? `<span class="violation-count">${nodeCount} instances</span>` : ''}
              ${learnMoreLinks}
            </div>
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

  function getViolationStatusText(impact) {
    switch (impact) {
      case 'critical':
        return 'STATUS: CRITICAL';
      case 'serious':
        return 'STATUS: CRITICAL';
      case 'moderate':
        return 'STATUS: MODERATE';
      case 'minor':
        return 'STATUS: MINOR';
      default:
        return 'STATUS: UNKNOWN';
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

  function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

})(jQuery, Drupal, drupalSettings);
