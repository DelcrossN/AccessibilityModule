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

  Drupal.behaviors.axeScanner = {
    attach: function (context, settings) {
      // Example: Run axe scan on a button click
      $(once('axe-scan', '#run-axe-scan', context)).on('click', function (e) {
        e.preventDefault();

        // Run axe-core scan (assumes axe-core is loaded)
        axe.run(document, {}, function (err, results) {
          if (err) {
            console.error('Axe error:', err);
            return;
          }

          const violations = results.violations;
          console.log('Accessibility violations:', violations);

          // Send violations to Ollama/Mistral for summarization
          const prompt = 'Summarize these accessibility violations: ' + JSON.stringify(violations);
          callOllamaMistral(prompt)
            .then(data => {
              console.log('Ollama/Mistral response:', data.response);
              // Optionally display the response in the UI
              $('#llm-results-wrapper').text(data.response);
            })
            .catch(err => {
              console.error('Ollama API error:', err);
            });
        });
      });
    }
  };

})(jQuery, Drupal);
