/**
 * @file
 * PDF export functionality for accessibility reports.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.accessibilityPdfExport = {
    attach: function (context, settings) {
      // Initialize PDF export functionality
      const exportButton = document.getElementById('export-report-btn');
      if (exportButton && !exportButton.hasAttribute('data-pdf-initialized')) {
        exportButton.setAttribute('data-pdf-initialized', 'true');
        exportButton.addEventListener('click', handlePdfExport);
      }
    }
  };

  /**
   * Handle PDF export button click.
   */
  async function handlePdfExport(event) {
    event.preventDefault();
    
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    try {
      // Update button state - no animation, just disable
      button.disabled = true;
      button.innerHTML = 'Generating PDF...';
      
      // Load jsPDF library if not already loaded
      if (typeof window.jsPDF === 'undefined') {
        await loadJsPDF();
      }
      
      // Generate and download PDF
      await generatePDF();
      
    } catch (error) {
      console.error('PDF export error:', error);
      showNotification('Error generating PDF: ' + error.message, 'error');
    } finally {
      // Reset button state
      button.disabled = false;
      button.innerHTML = originalText;
    }
  }

  /**
   * Load required PDF libraries dynamically.
   */
  function loadJsPDF() {
    return new Promise(async (resolve, reject) => {
      try {
        // Check if already loaded
        if (typeof window.jsPDF !== 'undefined' && typeof window.html2canvas !== 'undefined') {
          resolve();
          return;
        }

        // Load html2canvas first
        if (typeof window.html2canvas === 'undefined') {
          await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
          console.log('html2canvas loaded successfully');
        }

        // Load jsPDF
        if (typeof window.jsPDF === 'undefined') {
          await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
          console.log('jsPDF loaded successfully');
          
          if (typeof window.jspdf !== 'undefined') {
            window.jsPDF = window.jspdf.jsPDF;
          }
        }

        if (typeof window.jsPDF === 'undefined' || typeof window.html2canvas === 'undefined') {
          throw new Error('Failed to initialize PDF libraries');
        }

        resolve();
      } catch (error) {
        reject(error);
      }
    });
  }

  /**
   * Load a script dynamically.
   */
  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.crossOrigin = 'anonymous';
      
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
      
      document.head.appendChild(script);
    });
  }

  /**
   * Generate PDF from the current accessibility report using html2canvas.
   */
  async function generatePDF() {
    try {
      // Get the main report container
      const reportContainer = document.querySelector('.accessibility-comprehensive-report');
      if (!reportContainer) {
        throw new Error('Report container not found');
      }

      // Temporarily hide the export button to avoid it appearing in the PDF
      const exportButton = document.getElementById('export-report-btn');
      const originalButtonDisplay = exportButton ? exportButton.style.display : '';
      if (exportButton) {
        exportButton.style.display = 'none';
      }

      // Create a clone of the report for PDF generation
      const reportClone = reportContainer.cloneNode(true);
      
      // Apply PDF-specific styling to the clone with proper margins
      reportClone.style.cssText = `
        background: white;
        padding: 30px 40px;
        margin: 0;
        font-family: Arial, sans-serif;
        color: black;
        width: 750px;
        max-width: 750px;
        position: absolute;
        top: -9999px;
        left: -9999px;
        box-sizing: border-box;
        overflow: visible;
      `;

      // Append clone to body temporarily
      document.body.appendChild(reportClone);

      // Generate canvas from the cloned element with better settings
      const canvas = await window.html2canvas(reportClone, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        width: 750,
        height: reportClone.scrollHeight,
        scrollX: 0,
        scrollY: 0,
        x: 0,
        y: 0
      });

      // Remove the clone
      document.body.removeChild(reportClone);

      // Restore export button
      if (exportButton) {
        exportButton.style.display = originalButtonDisplay;
      }

      // Create PDF
      const { jsPDF } = window.jspdf;
      const imgData = canvas.toDataURL('image/png');
      
      // Calculate PDF dimensions with proper margins
      const pageWidth = 210; // A4 width in mm
      const pageHeight = 297; // A4 height in mm
      const margin = 15; // 15mm margins on all sides
      const contentWidth = pageWidth - (margin * 2);
      const contentHeight = pageHeight - (margin * 2);
      
      const imgWidth = contentWidth;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;
      
      // Create PDF document
      const doc = new jsPDF('p', 'mm', 'a4');
      
      let currentY = margin;
      let remainingHeight = imgHeight;
      let sourceY = 0;
      
      // Add content page by page to ensure proper margins
      while (remainingHeight > 0) {
        const availableHeight = contentHeight;
        const sliceHeight = Math.min(remainingHeight, availableHeight);
        
        // Calculate the portion of the image to use
        const canvasSliceHeight = (sliceHeight / imgHeight) * canvas.height;
        
        // Create a temporary canvas for this slice
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = canvas.width;
        tempCanvas.height = canvasSliceHeight;
        const tempCtx = tempCanvas.getContext('2d');
        
        // Draw the slice
        tempCtx.drawImage(canvas, 0, sourceY, canvas.width, canvasSliceHeight, 0, 0, canvas.width, canvasSliceHeight);
        
        // Add to PDF
        const sliceImgData = tempCanvas.toDataURL('image/png');
        doc.addImage(sliceImgData, 'PNG', margin, currentY, imgWidth, sliceHeight);
        
        remainingHeight -= sliceHeight;
        sourceY += canvasSliceHeight;
        
        if (remainingHeight > 0) {
          doc.addPage();
          currentY = margin;
        }
      }

      // Add metadata
      const reportTitle = document.querySelector('.report-title')?.textContent || 'Accessibility Report';
      const currentUrl = window.location.href;
      
      doc.setProperties({
        title: reportTitle,
        subject: 'Accessibility Compliance Report',
        author: 'Accessibility Suite',
        creator: 'AI-Powered Unified Accessibility Compliance Suite'
      });

      // Generate filename with timestamp
      const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
      const pageSlug = extractPageSlugFromUrl(currentUrl);
      const filename = `accessibility-report-${pageSlug}-${timestamp}.pdf`;

      // Save the PDF
      doc.save(filename);
      
      console.log('PDF generated successfully:', filename);
      
      // Show success message
      showNotification('PDF report generated successfully!', 'success');
      
    } catch (error) {
      console.error('Error generating PDF:', error);
      throw error;
    }
  }

  /**
   * Extract page slug from URL for filename.
   */
  function extractPageSlugFromUrl(url) {
    try {
      const urlObj = new URL(url);
      const pathParts = urlObj.pathname.split('/').filter(part => part);
      if (pathParts.length > 0) {
        return pathParts[pathParts.length - 1].replace(/[^a-zA-Z0-9-]/g, '');
      }
      return 'home';
    } catch (e) {
      return 'report';
    }
  }

  /**
   * Show notification message.
   */
  function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.pdf-export-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'pdf-export-notification';
    notification.textContent = message;
    
    const bgColor = type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff';
    
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: ${bgColor};
      color: white;
      padding: 12px 20px;
      border-radius: 6px;
      z-index: 10000;
      font-size: 14px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
      animation: slideInRight 0.3s ease-out;
    `;

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 3000);
  }

  /**
   * Get violation statistics from the page.
   */
  function getViolationStats() {
    return {
      total: parseInt(document.querySelector('.total-violations .card-number')?.textContent || '0'),
      critical: parseInt(document.querySelector('.critical-violations .card-number')?.textContent || '0'),
      serious: parseInt(document.querySelector('.serious-violations .card-number')?.textContent || '0'),
      moderate: parseInt(document.querySelector('.moderate-violations .card-number')?.textContent || '0'),
      minor: parseInt(document.querySelector('.minor-violations .card-number')?.textContent || '0')
    };
  }

  /**
   * Get violations list from the page.
   */
  function getViolationsList() {
    const violations = [];
    const violationElements = document.querySelectorAll('.violation-item');
    
    violationElements.forEach(element => {
      const id = element.querySelector('.violation-title')?.textContent || 'Unknown';
      const description = element.querySelector('.violation-description')?.textContent || 'No description';
      const impact = element.classList.contains('critical') ? 'critical' :
                    element.classList.contains('serious') ? 'serious' :
                    element.classList.contains('moderate') ? 'moderate' : 'minor';
      const nodeCountElement = element.querySelector('.violation-count');
      const nodeCount = nodeCountElement ? 
        parseInt(nodeCountElement.textContent.match(/\d+/)?.[0] || '0') : 0;
      
      violations.push({
        id: id.trim(),
        description: description.trim(),
        impact: impact,
        nodeCount: nodeCount
      });
    });
    
    return violations;
  }

  /**
   * Get color for impact level.
   */
  function getImpactColor(impact) {
    switch (impact) {
      case 'critical':
        return { r: 220, g: 53, b: 69 }; // Red
      case 'serious':
        return { r: 255, g: 193, b: 7 }; // Orange
      case 'moderate':
        return { r: 255, g: 235, b: 59 }; // Yellow
      case 'minor':
        return { r: 40, g: 167, b: 69 }; // Green
      default:
        return { r: 108, g: 117, b: 125 }; // Gray
    }
  }

})(jQuery, Drupal, drupalSettings);
