# Accessibility Test Violations Page

## Overview

This document describes the accessibility test page that has been added to the Accessibility module. This page is intentionally designed with numerous accessibility violations to test the effectiveness of accessibility scanning tools like axe-core.

## Access

The test page can be accessed through:

1. **Dashboard Link**: Visit `/admin/config/accessibility` and click on "Test Violations Page" 
2. **Direct URL**: `/accessibility/test-violations`
3. **Menu**: Admin → Configuration → Accessibility → Test Violations Page

## Types of Violations Included

### 1. Color Contrast Issues
- Light gray text on white background (insufficient contrast)
- Red text on red background (very poor contrast)

### 2. Form Accessibility Issues
- Input fields without labels
- Inputs with only placeholder text
- Select elements without labels
- Radio buttons without fieldset/legend
- Submit button with no accessible text

### 3. Image Accessibility Issues
- Images without alt text
- Decorative images with inappropriate alt text
- Complex charts without proper descriptions

### 4. Heading Structure Issues
- Missing h1 tag (uses h2 instead)
- Skipped heading levels (h3 to h5)
- Multiple h1 elements on the same page

### 5. Link and Navigation Issues
- Links with non-descriptive text ("click here")
- Links opening in new windows without warning
- Empty links with no content
- Links with only icon content and no accessible text

### 6. Table Accessibility Issues
- Tables without proper header markup
- Complex tables without scope attributes
- Missing table captions

### 7. Focus and Keyboard Issues
- Non-focusable interactive elements (clickable divs)
- Custom dropdowns without keyboard support
- Missing focus indicators

### 8. ARIA Issues
- Incorrect ARIA roles without keyboard support
- Missing ARIA labels for complex widgets
- Invalid ARIA attribute references
- Improper tab implementation

### 9. Language Issues
- Foreign language text without lang attributes
- Mixed content without proper language markup

### 10. Motion and Animation Issues
- Auto-playing animations without controls
- Flashing content that could trigger seizures
- Content that moves without user control

### 11. Additional Violations
- Duplicate IDs on the same page
- Empty headings
- Text that's too small to read
- Missing main landmark
- Iframes without titles
- Auto-playing audio
- Auto-changing content without user control

## Testing Tools

This page is designed to be scanned with:
- **axe-core**: The primary accessibility testing engine
- **WAVE**: Web Accessibility Evaluation Tool
- **Lighthouse**: Google's accessibility auditing tool
- **Screen readers**: NVDA, JAWS, VoiceOver, etc.

## Expected Results

When scanning this page, accessibility tools should detect:
- 50+ accessibility violations across multiple categories
- Various severity levels (critical, serious, moderate, minor)
- WCAG 2.1 AA compliance failures
- Section 508 compliance issues

## Usage Instructions

1. Navigate to the test page
2. Run your accessibility scanner of choice
3. Review the detected violations
4. Use this page to:
   - Test scanner accuracy
   - Train team members on accessibility issues
   - Demonstrate the importance of accessible design
   - Validate fix implementations

## Files Modified/Added

### New Files
- `templates/accessibility-test-violations.html.twig` - Main template
- `css/test-violations.css` - Styles contributing to violations
- `js/test-violations.js` - JavaScript with accessibility issues

### Modified Files
- `accessibility.routing.yml` - Added new route
- `src/Controller/AccessibilityController.php` - Added controller method
- `accessibility.module` - Added theme definition and library
- `accessibility.links.menu.yml` - Added menu link
- `templates/accessibility-dashboard.html.twig` - Added dashboard link

## Security Note

This page is accessible to all users with 'access content' permission. The violations are intentional and contained within this test page only. The rest of the module follows accessibility best practices.

## Maintenance

When updating this page:
1. Ensure violations remain detectable by current scanning tools
2. Add new violation types as they become relevant
3. Update this documentation when changes are made
4. Test with multiple accessibility scanners to verify detection

## Disclaimer

This page deliberately violates accessibility standards for testing purposes only. Do not use patterns from this page in production websites. Always follow WCAG guidelines and accessibility best practices for real content.
