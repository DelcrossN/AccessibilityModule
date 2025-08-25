/**
 * @file
 * Handles interactivity for the accessibility statistics page.
 */
(function ($, Drupal, once, Chart) {
  'use strict';

  // Explicitly register the zoom plugin with Chart.js.
  if (window.ChartZoom) {
    Chart.register(window.ChartZoom);
  }

  Drupal.behaviors.accessibilityStats = {
    attach: function (context, settings) {
      once('accessibility-stats', '.accessibility-stats', context).forEach(function (element) {
        const canvas = document.getElementById('accessibility-chart');
        if (!canvas) {
          return;
        }

        const ctx = canvas.getContext('2d');
        let accessibilityChart;
        let lastSliderValue = 1;

        /**
         * Populates the "Top Issues" list with a given set of issues.
         * @param {Array<Object>} issues - An array of issue objects.
         */
        function updateTopIssues(issues) {
          const $topIssuesList = $('#top-issues').empty();
          if (issues && issues.length > 0) {
            const sortedIssues = [...issues].sort((a, b) => b.issue_count - a.issue_count);
            sortedIssues.forEach(issue => {
              const description = issue.help || issue.description;
              // Add a class based on the impact for styling.
              const severityClass = issue.impact ? `issue-list__item--${issue.impact}` : '';
              const item = `<li class="issue-list__item ${severityClass}"><span class="issue-list__name">${$('<div>').text(description).html()}</span><span class="issue-list__count">${issue.issue_count}</span></li>`;
              $topIssuesList.append(item);
            });
          } else {
            $topIssuesList.append(`<li class="issue-list__item">${Drupal.t('No issues found for this period.')}</li>`);
          }
        }

        /**
         * Updates all statistic cards and lists on the page.
         * @param {object} data - The data object from the AJAX response.
         */
        function updateDashboard(data) {
          $('#total-issues').text(data.summary.total_issues || 0);
          $('#fixed-issues').text(data.summary.fixed_issues || 0);
          $('#high-priority').text(data.summary.high_priority || 0);
          $('#pages-scanned').text(data.summary.pages_scanned || 0);
          updateTopIssues(data.top_issues);
        }

        /**
         * Creates or updates the chart with new data.
         * @param {string} type - The chart type (bar, line, etc.).
         * @param {array} data - An array of {x, y} data points.
         */
        function renderChart(type, data) {
          if (accessibilityChart) {
            accessibilityChart.destroy();
          }
          accessibilityChart = new Chart(ctx, {
            type: type,
            data: {
              datasets: [{
                label: Drupal.t('Accessibility Issues'),
                data: data,
                backgroundColor: 'rgba(63, 81, 181, 0.5)',
                borderColor: 'rgba(63, 81, 181, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: type === 'line',
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                x: {
                  type: 'time',
                  time: {
                    displayFormats: {
                      hour: 'h a',
                      day: 'MMM d',
                      week: 'MMM d',
                      month: 'MMM yyyy',
                    },
                    tooltipFormat: 'MMMM d, yyyy h:mm a',
                  },
                  ticks: { maxRotation: 0, autoSkip: true, },
                  grid: { display: false }
                },
                y: { beginAtZero: true }
              },
              plugins: {
                legend: { display: false },
                zoom: {
                  pan: { enabled: true, mode: 'x', },
                  zoom: {
                    wheel: { enabled: false, },
                    pinch: { enabled: true },
                    mode: 'x',
                  }
                }
              }
            }
          });
        }

        let weekStartDate = null;

        /**
         * Fetches new data from the server based on selected filters.
         */
        function fetchData() {
          const timeframe = $('#timeframe').val();
          const chartType = $('#chart-type').val();
          const $loader = $('#stats-loading');
          let ajaxData = { chart_type: chartType };

          if (timeframe === 'custom') {
            const viewBy = $('#view-by').val();
            ajaxData.timeframe = 'custom';
            ajaxData.view_by = viewBy;

            if (viewBy === 'day') {
              const customDate = $('#custom-date-picker').val();
              if (!customDate) return;
              ajaxData.date = customDate;
            }
            else if (viewBy === 'week') {
              const endDate = $('#custom-date-picker').val();
              if (!weekStartDate || !endDate) return;
              ajaxData.start_date = weekStartDate;
              ajaxData.end_date = endDate;
            }
            else {
              return;
            }
          }
          else {
            ajaxData.timeframe = timeframe;
          }

          $loader.addClass('is-active');

          $.ajax({
            url: Drupal.url('admin/reports/accessibility/stats/ajax'),
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: function (data) {
              // Remove scan-specific styling before populating with DB data.
              $('#top-issues').removeClass('scan-results-active');
              updateDashboard(data);
              renderChart(chartType, data.chart.data);
              const zoomSlider = document.getElementById('zoom-slider');
              if (zoomSlider) {
                zoomSlider.value = 1;
              }
              lastSliderValue = 1;
            },
            error: function () {
              Drupal.message(Drupal.t('An error occurred while fetching statistics.'), 'error');
            },
            complete: function () {
              $loader.removeClass('is-active');
            }
          });
        }

        /**
         * Sets up all event listeners and performs the initial data fetch.
         */
        function initializeDashboard() {
          const $timeframeSelect = $('#timeframe');
          const $viewByWrapper = $('#view-by-wrapper');
          const $viewBySelect = $('#view-by');
          const $datePickerWrapper = $('#custom-date-picker-wrapper');
          const $datePicker = $('#custom-date-picker');
          const $datePickerLabel = $('#custom-date-picker-label');
          const $scanNowBtn = $('#scan-now-btn');
          const $emailReportBtn = $('#email-report-btn');

          // --- UI LOGIC ---
          $timeframeSelect.on('change', function () {
            $viewByWrapper.hide();
            $datePickerWrapper.hide();
            $viewBySelect.val('');

            if ($(this).val() === 'custom') {
              $viewByWrapper.css('display', 'flex');
            } else {
              fetchData();
            }
          });

          $viewBySelect.on('change', function () {
            const view = $(this).val();
            weekStartDate = null;
            $datePicker.val('');
            $datePicker.removeAttr('min max');

            if (view === 'day') {
              $datePickerLabel.text(Drupal.t('Select Day'));
              $datePickerWrapper.css('display', 'flex');
            } else if (view === 'week') {
              $datePickerLabel.text(Drupal.t('Select Start Date'));
              $datePickerWrapper.css('display', 'flex');
            } else {
              $datePickerWrapper.hide();
            }
          });

          $datePicker.on('change', function() {
            const view = $viewBySelect.val();
            const selectedDate = $(this).val();

            if (view === 'day') {
              fetchData();
            }
            else if (view === 'week') {
              if (!weekStartDate) {
                weekStartDate = selectedDate;
                $datePickerLabel.text(Drupal.t('Select End Date'));

                const startDate = new Date(selectedDate + 'T00:00:00');
                const maxEndDate = new Date(startDate);
                maxEndDate.setDate(maxEndDate.getDate() + 6);

                const formatDate = (date) => date.toISOString().split('T')[0];
                $datePicker.attr('min', formatDate(startDate));
                $datePicker.attr('max', formatDate(maxEndDate));
              }
              else {
                fetchData();
              }
            }
          });

          $('#chart-type').on('change', fetchData);

          // --- QUICK ACTIONS ---
          if ($scanNowBtn.length) {
            $scanNowBtn.on('click', function() {
              const $button = $(this);
              if (typeof axe === 'undefined') {
                Drupal.message(Drupal.t('Axe scanner is not available.'), 'error');
                return;
              }

              const originalText = $button.html();
              $button.html(`<span class="icon icon-scan"></span> ${Drupal.t('Scanning...')}`).prop('disabled', true);

              axe.run({
                exclude: [['#toolbar-bar'], ['.accessibility-stats']]
              }).then(results => {
                const violations = results.violations.map(violation => ({
                  help: violation.help,
                  issue_count: violation.nodes.length,
                  // Capture the severity for styling.
                  impact: violation.impact
                }));

                // Add a class to the parent list to activate special styling.
                $('#top-issues').addClass('scan-results-active');
                updateTopIssues(violations);
                Drupal.message(Drupal.t('On-page scan complete. Found @count violation types.', {'@count': violations.length}));
              }).catch(err => {
                console.error('Axe scan error:', err);
                Drupal.message(Drupal.t('An error occurred during the accessibility scan.'), 'error');
              }).finally(() => {
                $button.html(originalText).prop('disabled', false);
              });
            });
          }

          if ($emailReportBtn.length) {
            $emailReportBtn.on('click', function() {
              const reportSummary = `Accessibility Report Summary
------------------------------------
Timeframe: ${$('#timeframe option:selected').text().trim()}
Total Issues: ${$('#total-issues').text()}
High Priority Issues: ${$('#high-priority').text()}
Pages Scanned: ${$('#pages-scanned').text()}

Top Issues Found:
${$('#top-issues .issue-list__item').map(function() {
                const name = $(this).find('.issue-list__name').text();
                const count = $(this).find('.issue-list__count').text();
                if (name && count && !$(this).text().includes(Drupal.t('No issues found'))) {
                  return `- ${name} (${count} occurrences)`;
                }
                return null;
              }).get().join('\n')}
              `.trim();

              const subject = "Accessibility Statistics Report";
              const mailtoLink = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(reportSummary)}`;
              window.location.href = mailtoLink;
            });
          }

          // --- ZOOM CONTROLS ---
          const $zoomSlider = $('#zoom-slider');
          const $resetZoomBtn = $('#reset-zoom-btn');
          if ($zoomSlider.length && $resetZoomBtn.length) {
            $zoomSlider.on('input', () => {
              if (accessibilityChart) {
                const newSliderValue = parseFloat($zoomSlider.val());
                if (lastSliderValue > 0) {
                  const zoomFactor = newSliderValue / lastSliderValue;
                  accessibilityChart.zoom(zoomFactor);
                }
                lastSliderValue = newSliderValue;
              }
            });
            $resetZoomBtn.on('click', () => {
              if (accessibilityChart) {
                accessibilityChart.resetZoom();
                $zoomSlider.val(1);
                lastSliderValue = 1;
              }
            });
          }

          fetchData();
        }

        initializeDashboard();
      });
    }
  };

})(jQuery, Drupal, once, Chart);
