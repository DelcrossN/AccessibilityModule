/**
 * @file
 * Handles interactivity for the accessibility statistics page.
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.accessibilityStats = {
    attach: function (context, settings) {
      console.log('Accessibility stats behavior attached');
      console.log('Settings:', settings);
      console.log('drupalSettings:', drupalSettings);
      
      $('.accessibility-stats', context).once().each(function () {
        console.log('Processing accessibility stats container');
        const canvas = document.getElementById('accessibility-chart');
        if (!canvas) {
          console.error('Canvas element with ID "accessibility-chart" not found');
          return;
        }
        console.log('Canvas found:', canvas);

        // Check if Chart.js is available
        if (typeof Chart === 'undefined' && typeof window.Chart === 'undefined') {
          console.error('Chart.js is not loaded');
          canvas.parentNode.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:1.2em;color:#888;">Chart.js library not loaded</div>';
          return;
        }

        const ChartJS = typeof Chart !== 'undefined' ? Chart : window.Chart;
        const ctx = canvas.getContext('2d');
        let accessibilityChart;
        let lastSliderValue = 1;

        // Explicitly register the zoom plugin with Chart.js.
        if (window.ChartZoom && ChartJS.register) {
          ChartJS.register(window.ChartZoom);
        }

        // Initialize chart
        function initChart() {
          const ctx = document.getElementById('accessibility-chart');
          if (!ctx) {
            console.error('Chart canvas not found');
            return;
          }

          // Get chart data from drupalSettings
          const chartData = drupalSettings.accessibility && drupalSettings.accessibility.chartData ? 
                           drupalSettings.accessibility.chartData : [];
          
          console.log('Chart data:', chartData);
          
          let labels = [];
          let counts = [];

          // If we have data from backend, use it
          if (chartData && chartData.length > 0) {
            labels = chartData.map(item => item.date);
            counts = chartData.map(item => item.count);
          } else {
            // Generate last 7 days with zero counts
            const today = new Date();
            for (let i = 6; i >= 0; i--) {
              const date = new Date(today);
              date.setDate(date.getDate() - i);
              labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
              counts.push(0);
            }
          }

          // Always render the chart, even if all counts are zero
          const data = {
            labels: labels,
            datasets: [{
              label: 'Daily Scans Performed',
              data: counts,
              borderColor: 'rgb(54, 162, 235)',
              backgroundColor: 'rgba(54, 162, 235, 0.2)',
              tension: 0.1,
              fill: false
            }]
          };

          const config = {
            type: 'line',
            data: data,
            options: {
              responsive: true,
              maintainAspectRatio: false,
              layout: {
                padding: 20
              },
              plugins: {
                title: {
                  display: false
                },
                legend: {
                  display: true,
                  position: 'top'
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    stepSize: 1
                  }
                },
                x: {
                  ticks: {
                    maxRotation: 0,
                    autoSkip: true
                  }
                }
              }
            }
          };

          try {
            accessibilityChart = new ChartJS(ctx, config);
            console.log('Chart initialized successfully:', accessibilityChart);
            
            // Force chart to resize to container dimensions
            setTimeout(() => {
              if (accessibilityChart) {
                accessibilityChart.resize();
              }
            }, 100);
          } catch (error) {
            console.error('Error creating chart:', error);
            ctx.parentNode.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:1.2em;color:#888;">Error loading chart: ' + error.message + '</div>';
            return;
          }
          // Add chart type selector functionality
          const chartTypeSelector = document.getElementById('chart-type');
          if (chartTypeSelector) {
            chartTypeSelector.addEventListener('change', function() {
              if (!accessibilityChart) return;
              
              const newType = this.value;
              accessibilityChart.config.type = newType;
              
              // Update dataset styling based on chart type
              if (newType === 'bar') {
                accessibilityChart.data.datasets[0].backgroundColor = 'rgba(54, 162, 235, 0.6)';
                accessibilityChart.data.datasets[0].borderColor = 'rgb(54, 162, 235)';
                accessibilityChart.data.datasets[0].borderWidth = 1;
              } else {
                accessibilityChart.data.datasets[0].backgroundColor = 'rgba(54, 162, 235, 0.2)';
                accessibilityChart.data.datasets[0].borderColor = 'rgb(54, 162, 235)';
                accessibilityChart.data.datasets[0].borderWidth = 2;
              }
              
              accessibilityChart.update();
            });
          }
        }

        // Initialize the chart on page load
        initChart();

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
          // Update violation stats cards
          $('#unique-pages-count').text(data.summary.unique_pages || 0);
          $('#total-violations-count').text(data.summary.total_violations || 0);
          $('#critical-violations-count').text(data.summary.critical_violations || 0);
          $('#serious-violations-count').text(data.summary.serious_violations || 0);
          $('#moderate-violations-count').text(data.summary.moderate_violations || 0);
          $('#minor-violations-count').text(data.summary.minor_violations || 0);
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
          accessibilityChart = new ChartJS(ctx, {
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

})(jQuery, Drupal);
