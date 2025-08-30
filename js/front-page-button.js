/**
 * @file
 * Adds a button below the article content to navigate to test violations page.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Add front page button behavior.
   */
  Drupal.behaviors.accessibilityFrontPageButton = {
    attach: function (context, settings) {
      // Only run once per page
      $('body', context).once().each(function () {
        console.log('Adding accessibility front page button...');
        
        // Create a proper button container
        var buttonContainer = $('<div class="accessibility-test-container"></div>');
        var button = $('<button type="button" class="accessibility-test-button">Run Accessibility Test</button>');
        
        // Add click handler to navigate to violations page
        button.click(function(e) {
          e.preventDefault();
          window.location.href = '/accessibility/test-violations';
        });
        
        // Style the container
        buttonContainer.css({
          'margin': '40px 0',
          'padding': '30px',
          'background': '#f8f9fa',
          'border': '2px solid #2e86c1',
          'border-radius': '8px',
          'text-align': 'center',
          'box-shadow': '0 2px 4px rgba(0,0,0,0.1)'
        });
        
        // Style the button
        button.css({
          'background': '#2e86c1',
          'color': 'white',
          'border': 'none',
          'padding': '15px 30px',
          'font-size': '18px',
          'font-weight': 'bold',
          'border-radius': '5px',
          'cursor': 'pointer',
          'transition': 'all 0.3s ease',
          'text-transform': 'uppercase',
          'letter-spacing': '1px'
        });
        
        // Add hover effects
        button.hover(
          function() {
            $(this).css({
              'background': '#1f5f8b',
              'transform': 'translateY(-2px)',
              'box-shadow': '0 4px 8px rgba(0,0,0,0.2)'
            });
          },
          function() {
            $(this).css({
              'background': '#2e86c1',
              'transform': 'translateY(0)',
              'box-shadow': 'none'
            });
          }
        );
        
        // Add button to container
        buttonContainer.append('<h3 style="color: #2e86c1; margin-bottom: 15px;">Accessibility Testing</h3>');
        buttonContainer.append('<p style="margin-bottom: 20px; color: #666;">Test the accessibility scanner with intentional violations</p>');
        buttonContainer.append(button);
        
        // Find the article content and add button after it
        var articleContent = $('.node--type-article .node__content, article .content, .node .content, main .content');
        if (articleContent.length > 0) {
          articleContent.last().after(buttonContainer);
          console.log('Button added after article content');
        } else {
          // Fallback - add after main content area
          var mainContent = $('main, .main-content, .content, #content');
          if (mainContent.length > 0) {
            mainContent.first().append(buttonContainer);
            console.log('Button added to main content area');
          } else {
            // Final fallback - add to body
            $('body').append(buttonContainer);
            console.log('Button added to body as fallback');
          }
        }
        
        console.log('Front page button added successfully');
      });
    }
  };

})(jQuery, Drupal);
