# AI-Powered Unified Accessibility Compliance Suite

A comprehensive Drupal module that serves as a unified accessibility compliance suite, combining automated scanning, AI-powered analysis, and detailed reporting to help maintain WCAG 2.1 AA compliance across Drupal websites.

## Overview

The **AI-Powered Unified Accessibility Compliance Suite** is an advanced Drupal module designed to streamline accessibility compliance management. By integrating industry-standard scanning tools with artificial intelligence, this module provides a complete solution for identifying, analyzing, and resolving accessibility issues across your website.

The module functions as a centralized accessibility hub, offering real-time scanning capabilities through the Deque Axe-core API, intelligent violation analysis using Google Gemini Flash LLM, and comprehensive reporting tools. It features an intuitive sidebar block system that can be placed on any page to provide immediate accessibility feedback and violation detection.

## Key Features

### Automated Accessibility Scanning
- Integration with **Deque Axe-core API** for comprehensive WCAG 2.1 compliance testing
- Real-time page scanning with detailed violation reporting
- Intelligent caching system to optimize performance
- Support for both automated and on-demand scanning

### AI-Powered Analysis
- **Google Gemini Flash LLM integration** for intelligent violation analysis
- Context-aware suggestions for resolving accessibility issues
- Detailed explanations of why violations matter and how to fix them
- AI-generated best practices and prevention strategies

### Interactive Sidebar Block System
- Accessible tools sidebar that can be placed on any page
- Real-time violation scanning and display in a responsive popup interface
- Visual highlighting of accessibility issues directly on web pages
- Option to redirect to detailed Axe documentation for each violation type
- AI analysis feature for personalized fix suggestions

### Comprehensive Dashboard and Reporting
- Centralized accessibility dashboard with four main navigation options
- Detailed accessibility reports showing violations by severity and page
- Statistical analysis with trend tracking and performance metrics
- Export capabilities for compliance documentation

### Flexible Configuration Management
- Settings page for enabling AI functionality through LLM API keys
- Support for Google Gemini Flash with planned expansion to open-source models
- Configurable scan frequency and automation options
- Debug mode for development and troubleshooting

## Installation and Setup

### Requirements
- **Drupal**: 9.x, 10.x, or 11.x
- **PHP**: 7.4 or higher
- **Dependencies**: jQuery UI module, Charts module

### Installation Steps

1. **Enable the Module**
   ```bash
   drush en accessibility -y
   ```
   Or enable via the Drupal admin interface at `/admin/modules`

2. **Place the Sidebar Block**
   After installation, navigate to `/admin/structure/block` and place the "Accessibility Tools" block in your desired region (typically sidebar). Configure the block to display on specific pages or page patterns where you want accessibility scanning functionality available.

3. **Configure API Settings**
   Navigate to `/admin/config/accessibility/settings` to configure:
   - Google Gemini Flash API key for AI-powered analysis
   - API endpoint settings for external accessibility services
   - Scan automation preferences
   - Display and widget positioning options

## Core Functionality

### Dashboard Overview
The main accessibility dashboard at `/admin/config/accessibility` provides four primary navigation options:

1. **Settings Configuration**: Access to all module configuration options
2. **Accessibility Reports**: Detailed violation reports and analysis tools  
3. **Statistics Page**: Performance metrics and trend analysis
4. **Test Violations Page**: Controlled environment for testing scan functionality

### Accessibility Reports Section
The comprehensive reporting system works by:
- Scanning specified pages using the Deque Axe-core API
- Categorizing violations by severity (Critical, Serious, Moderate, Minor)
- Providing detailed descriptions and remediation guidance for each issue
- Offering export capabilities for compliance documentation
- Maintaining historical records for trend analysis

Users can generate reports for individual pages or site-wide assessments, with each report including violation counts, affected elements, and recommended fixes.

### Statistics Page
The statistics page provides visual analytics including:
- Daily scan count tracking with interactive charts
- Violation trend analysis over time
- Performance metrics and scanning efficiency data
- Cache utilization and system health indicators

*Note: The statistics page is currently in active development and requires additional features to be completely fleshed out.*

### Configuration and Settings
The settings page at `/admin/config/accessibility/settings` enables AI functionality through:
- Google Gemini Flash API key configuration (currently supported LLM)
- Model selection and parameter tuning (temperature, token limits)
- Cache duration settings for AI responses
- Integration preferences for external accessibility services

Future development will expand support to include open-source LLM models for greater flexibility and cost-effectiveness.

### Test Violations Page
Located at `/accessibility/test-violations`, this page provides:
- A controlled testing environment with intentional accessibility violations
- Functionality verification for the scanning system
- Calibration tools to ensure proper violation detection
- Sample scenarios for testing AI analysis capabilities

This page helps administrators verify that the accessibility scanning is working correctly and identify any configuration issues that may require adjustment.

### Interactive Popup System
The accessibility tools sidebar creates an interactive popup interface that:
- Performs live accessibility scans using the Deque Axe-core API
- Displays violations in a categorized, easy-to-understand format
- Provides visual highlighting of problematic elements on the current page
- Offers direct links to detailed Axe documentation for each violation type
- Includes AI analysis functionality for personalized remediation suggestions

The popup system works on any page where the sidebar block is placed, providing immediate accessibility feedback without requiring navigation away from the current content.


## Technical Architecture

### Backend Components
- **AccessibilityController**: Main dashboard and reporting functionality
- **AccessibilityStatsController**: Statistical analysis and chart data management
- **AccessibilityCacheController**: Intelligent caching for performance optimization
- **ChatbotService**: AI-powered analysis using Google Gemini Flash
- **AccessibilityCacheService**: Data persistence and retrieval management

### Frontend Components
- **Axe Scanner Integration**: JavaScript-based real-time accessibility scanning
- **Chart.js Visualization**: Interactive statistical charts and data presentation
- **Responsive Popup System**: Sidebar-based violation display interface
- **Progressive Enhancement**: Accessible design with graceful degradation

### Data Management
- Intelligent caching with configurable TTL settings
- Structured violation storage with severity categorization
- Historical data tracking for trend analysis
- Optimized database queries for large-scale deployments

## Development and Customization

### Template Customization
Override default templates by copying to your theme:
- `accessibility-dashboard.html.twig`: Main dashboard layout
- `accessibility-report.html.twig`: Detailed violation reports
- `accessibility-stats.html.twig`: Statistics and analytics display

### JavaScript Extension Points
- `axe-scan-sidebar.js`: Popup behavior and scanning interface
- `accessibility-stats.js`: Chart customization and data visualization
- Custom integrations with existing site JavaScript

### API Extensions
The module provides hooks and services for:
- Custom violation analysis algorithms
- Additional LLM provider integrations
- Extended reporting formats and export options
- Third-party accessibility service connections

## Troubleshooting

### Common Issues

**Sidebar Block Not Displaying**
- Verify block placement and region configuration
- Check page visibility settings in block configuration
- Ensure required JavaScript libraries are loading

**API Connection Failures**
- Validate Google Gemini Flash API key configuration
- Check network connectivity and firewall settings
- Review API quota limits and usage restrictions

**Scanning Not Working**
- Verify Axe-core CDN connectivity
- Check browser console for JavaScript errors
- Test with the dedicated test violations page

### Performance Optimization
- Adjust cache TTL settings based on site traffic
- Configure scanning frequency for optimal resource usage
- Monitor API usage to stay within quota limits
- Use the statistics page to identify performance bottlenecks

## Future Development Scope

### Planned Enhancements
- **Enhanced Statistics Dashboard**: Complete feature set with advanced analytics, custom date ranges, and comprehensive performance metrics
- **Open-Source LLM Integration**: Migration from proprietary models to open-source alternatives like Llama, Mistral, and locally-hosted solutions for greater cost-effectiveness and privacy
- **Advanced AI Analysis**: More sophisticated violation pattern recognition and automated fix suggestions
- **Multi-Language Support**: Internationalization for global accessibility compliance
- **Integration APIs**: Enhanced connectivity with external accessibility management platforms
- **Automated Remediation**: AI-powered automatic fix application for common violations
- **Compliance Reporting**: Automated generation of WCAG 2.1 compliance reports for legal requirements

### Long-term Vision
The project aims to become the definitive accessibility solution for Drupal, providing not just detection and analysis but active remediation capabilities. Future versions will incorporate machine learning for predictive accessibility issues, automated testing integration with CI/CD pipelines, and comprehensive accessibility education resources.

## Contributing

This project welcomes contributions from the Drupal accessibility community:

### Development Guidelines
- Follow Drupal coding standards and best practices
- Include comprehensive tests for new functionality
- Maintain accessibility compliance in all interface elements
- Update documentation for API changes and new features

### Areas for Contribution
- Additional LLM provider integrations
- Enhanced statistical analysis features
- Mobile-responsive interface improvements
- Performance optimization and caching strategies

## Support and Documentation

For support, bug reports, and feature requests:
- **Issue Queue**: Project issue tracking
- **Community Forums**: Drupal accessibility discussions
- **Documentation**: Comprehensive module documentation
- **Testing Environment**: Use the built-in test violations page for functionality verification

## License

This project is licensed under the GNU General Public License v2.0 or later.

## Acknowledgements

Developed as part of **Google Summer of Code 2025** by [**Neil Dwivedi**] under the mentorship of [**Mr. Krish Gaur**](https://www.drupal.org/u/ubulinux) from the Drupal community.

Special recognition to:
- The Drupal Association
- Deque Systems for Axe-core integration
- Google for Gemini Flash API access
- Community contributors
