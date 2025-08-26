(function ($) {
  'use strict';

  $(document).ready(function() {
    // Find all possible axe scan buttons
    console.log('=== DEBUG: Looking for Axe scan buttons ===');

    $('*').each(function() {
      var text = $(this).text().toLowerCase();
      if (text.includes('axe') || text.includes('scan') || text.includes('accessibility')) {
        console.log('Found potential button:', this, $(this).text());
      }
    });

    // Log all buttons in sidebar
    $('.region-sidebar-first button, .region-sidebar-first a').each(function() {
      console.log('Sidebar button/link:', this, $(this).text());
    });
  });

})(jQuery);
