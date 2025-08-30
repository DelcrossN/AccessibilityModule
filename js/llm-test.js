(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.llmTest = {
    attach: function (context, settings) {
      // This behavior will be processed by Drupal's AJAX system automatically
      // since we're using the form's #ajax property.
    }
  };

})(jQuery, Drupal, drupalSettings);
