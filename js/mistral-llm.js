// web/core/modules/custom/accessibility/js/mistral-llm.js

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
      .then(response => response.json())
      .then(data => data.response);
  }

  Drupal.behaviors.mistralLlm = {
    attach: function (context, settings) {
      const form = once('mistral-llm', '#llm-test-form', context);

      if (form.length) {
        const loadingIndicator = `
          <div class="llm-loading" style="display: none;">
            <div class="progress-spinner"></div>
            <div class="progress-message">Analyzing content with Mistral 7B...</div>
          </div>
        `;

        $(form).append(loadingIndicator);

        $(form).on('ajax:beforeSend', function () {
          $('.llm-loading').show();
          $('#llm-results-wrapper').html('');
        });

        $(form).on('ajax:complete', function () {
          $('.llm-loading').hide();
        });

        // Add copy button functionality
        $(document).on('click', '.copy-result', function(e) {
          e.preventDefault();
          const resultText = $(this).siblings('.analysis-content').text();
          navigator.clipboard.writeText(resultText).then(function() {
            Drupal.message('Analysis copied to clipboard', { type: 'status' });
          });
        });
      }
    }
  };

  // Expose the function if needed elsewhere
  window.callOllamaMistral = callOllamaMistral;

})(jQuery, Drupal);
