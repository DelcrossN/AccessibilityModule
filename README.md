# AI-Powered Unified Accessibility Compliance Suite

A comprehensive Drupal module for enhancing web accessibility using AI-driven tools and automated scanning capabilities.

## Overview

The **AI-Powered Unified Accessibility Compliance Suite** is a sophisticated Drupal module designed to help site administrators identify, track, and resolve accessibility issues across their websites. By integrating with industry-standard accessibility testing tools like Axe-core and providing intelligent reporting capabilities, this module streamlines the process of maintaining WCAG 2.1 AA compliance.

## Key Features

### 🎯 **AI-Powered Accessibility Analysis**
- **Mistral 7B LLM Integration**: Advanced AI-powered accessibility analysis using Mistral 7B through OpenRouter
- **Automated Issue Detection**: Intelligent identification of accessibility issues beyond standard scanning
- **AI-Generated Recommendations**: Context-aware suggestions for resolving accessibility issues
- **Natural Language Processing**: Understands and explains accessibility issues in plain language

### 🔍 **Automated Accessibility Scanning**
- Integration with **Axe-core** for comprehensive accessibility testing
- Real-time scanning of web pages with detailed violation reporting
- Support for WCAG 2.1 Level A and AA compliance standards
- Intelligent caching system to optimize scan performance

### 📊 **Dynamic Dashboard & Reporting**
- Interactive dashboard with visual statistics and trend analysis
- Detailed accessibility reports with severity-based categorization
- Export capabilities for compliance documentation
- Historical tracking of accessibility improvements over time

### 🔧 **Comprehensive Configuration**
- Flexible API integration settings for external accessibility services
- Configurable scan frequency and automation options
- Customizable display settings and widget positioning
- Debug mode for development and troubleshooting

### 🎨 **User-Friendly Interface**
- Clean, responsive dashboard design with accessibility in mind
- Color-coded issue severity indicators (Critical, Serious, Moderate, Minor)
- Detailed violation descriptions with actionable remediation guidance
- One-click scanning functionality directly from the admin interface

### ⚡ **Performance Optimized**
- Smart caching mechanisms to reduce API calls and improve response times
- Asynchronous scanning capabilities
- Efficient data storage and retrieval systems
- Minimal impact on site performance

## Technical Architecture

### Backend Components
- **AccessibilityController**: Manages dashboard, statistics, and reporting functionality
- **LlmAnalysisService**: Handles AI-powered accessibility analysis using Mistral 7B
- **AccessibilityApiClient**: Handles external API integrations and data processing
- **AccSettingsForm**: Provides comprehensive configuration management including LLM settings
- **LlmTestController**: Test interface for LLM integration and analysis
- **Service Container Integration**: Proper dependency injection and service management

### Frontend Components
- **Axe Scanner Integration**: JavaScript-based accessibility scanning
- **Chart.js Visualization**: Interactive charts and graphs for data presentation
- **Responsive Templates**: Mobile-friendly Twig templates with inline styling
- **Progressive Enhancement**: Graceful degradation for various browser capabilities

### Data Management
- **Intelligent Caching**: 1-hour TTL with tag-based cache invalidation
- **Sample Data Generation**: Fallback system for testing and demonstration
- **Flexible API Integration**: Support for multiple accessibility service providers
- **Structured Reporting**: Standardized data formats for consistent reporting

## Installation & Setup

### Requirements
- **Drupal**: 9.x, 10.x, or 11.x
- **PHP**: 7.4 or higher
- **Dependencies**: jQuery UI module

### Installation Steps

1. **Download and Install**
   ```bash
   # Via Composer (recommended)
   composer require drupal/accessibility
   
   # Or download and extract to modules/contrib/accessibility
   ```

2. **Enable the Module**
   ```bash
   drush en accessibility -y
   ```
   Or enable via the Drupal admin interface at `/admin/modules`

3. **Configure Settings**
   Navigate to `/admin/config/accessibility/settings` to configure:
   - API credentials for external services
   - Scan frequency and automation settings
   - Display preferences and widget options
   - Debug and advanced settings

4. **Access the Dashboard**
   Visit `/admin/config/accessibility` to start using the accessibility tools

## Usage Guide

### Dashboard Overview
The main dashboard provides:
- **Quick Actions**: One-click accessibility scanning
- **Statistics Overview**: Visual representation of accessibility metrics
- **Navigation Links**: Easy access to configuration and detailed reports
- **Real-time Updates**: Live scanning results and progress tracking

### Running Accessibility Scans
1. Navigate to the accessibility dashboard
2. Click "Run Accessibility Scan" to perform a real-time scan
3. View results in the browser console or generate detailed reports
4. Access historical data through the statistics page

### Generating Reports
- **Individual Page Reports**: `/admin/config/accessibility/report/{path}`
- **Site-wide Statistics**: `/admin/config/accessibility/statistics`
- **Export Options**: PDF, CSV, and WCAG-compliant documentation formats

### LLM Integration Guide

### Enabling LLM Analysis

1. Navigate to `/admin/config/accessibility/settings`
2. Expand the "LLM Configuration" section
3. Enable "Enable LLM Analysis"
4. Enter your OpenRouter API key
5. Configure the desired LLM model (Mistral 7B is recommended for free tier)
6. Adjust temperature and max tokens as needed
7. Save the configuration

### LLM Configuration Options

- **LLM Provider**: Select between different LLM providers (currently supports OpenRouter)
- **Model**: Choose the LLM model (Mistral 7B, Mixtral 8x7B, etc.)
- **Temperature**: Control the creativity/conservativeness of the AI (0.0 to 2.0)
- **Max Tokens**: Limit the response length (100-4000 tokens)
- **Cache TTL**: How long to cache AI responses (in seconds)

### Testing LLM Integration

1. Navigate to `/admin/config/accessibility/llm-test`
2. Enter HTML content to analyze or use the provided example
3. Click "Analyze with AI"
4. Review the AI-generated analysis and recommendations

### Configuration Options

#### API Settings
- **API Key**: Authentication key for accessibility services
- **LLM API Key**: OpenRouter API key for AI analysis
- **API Endpoint**: Base URL for accessibility API integration
- **Timeout Settings**: Request timeout and retry configurations

#### Scan Settings
- **Auto Scan**: Enable/disable automatic scanning
- **Scan Frequency**: Hourly, daily, or weekly automated scans
- **Scope Configuration**: Define which pages to include/exclude

#### Display Settings
- **Widget Visibility**: Show/hide accessibility widget on frontend
- **Widget Position**: Left or right positioning options
- **Theme Integration**: Customize appearance to match site design

## API Integration

The module supports integration with various accessibility testing services:

### Default Configuration
```yaml
# Example API configuration
api_endpoint: 'https://api.accessibility.com/v1'
api_key: 'your-api-key-here'
timeout: 30
cache_ttl: 3600
```

### Custom API Providers
The module's flexible architecture allows integration with:
- **Deque Axe API**: Industry-standard accessibility testing
- **WAVE API**: WebAIM's accessibility evaluation tool
- **Custom Services**: Extensible framework for proprietary solutions

## Development & Customization

### Extending Functionality
The module provides several extension points:
- **Custom Scan Providers**: Implement additional scanning services
- **Report Formatters**: Create custom export formats
- **Theme Integration**: Override templates and styling
- **API Endpoints**: Add custom REST endpoints for external integrations

### Template Customization
Override default templates by copying to your theme:
- `accessibility-dashboard.html.twig`: Main dashboard layout
- `accessibility-report.html.twig`: Detailed report display
- `accessibility-stats.html.twig`: Statistics and charts

### JavaScript Customization
Extend the frontend functionality:
- `axe-scanner.js`: Modify scanning behavior
- Custom Chart.js configurations for data visualization
- Progressive enhancement for accessibility features

## Troubleshooting

### Common Issues

**Axe-core Not Loading**
- Verify CDN connectivity
- Check browser console for JavaScript errors
- Ensure jQuery and Drupal core libraries are loaded

**API Connection Failures**
- Validate API credentials and endpoint URLs
- Check network connectivity and firewall settings
- Review cache settings and clear if necessary

**Performance Issues**
- Adjust cache TTL settings
- Optimize scan frequency for large sites
- Consider implementing queue-based scanning for high-traffic sites

### Debug Mode
Enable debug mode in the configuration to:
- View detailed API request/response logs
- Monitor cache hit/miss ratios
- Track performance metrics and bottlenecks

## Contributing

This project welcomes contributions from the Drupal community:

### Development Setup
1. Clone the repository
2. Set up a local Drupal development environment
3. Install development dependencies
4. Follow Drupal coding standards and best practices

### Contribution Guidelines
- Follow Drupal coding standards
- Include comprehensive tests for new features
- Update documentation for any API changes
- Ensure accessibility compliance in all contributions

## Roadmap

### Planned Features
- **AI-Powered Remediation**: Automated fix suggestions using machine learning
- **Multi-language Support**: Internationalization and localization
- **Advanced Analytics**: Deeper insights and trend analysis
- **Integration APIs**: Enhanced third-party service connectivity
- **Mobile App**: Companion mobile application for on-the-go monitoring

### Version History
- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Enhanced reporting and API integration
- **v1.2.0**: Performance optimizations and UI improvements

## Acknowledgements

This project was developed as part of **Google Summer of Code 2025** under the mentorship of [**Mr. Krish Gaur**](https://www.drupal.org/u/ubulinux) from the **Drupal community**.

Special thanks to:
- The Drupal Accessibility community
- Deque Systems for Axe-core integration
- Contributors and beta testers

## License

This project is licensed under the GNU General Public License v2.0 or later - see the [LICENSE](LICENSE) file for details.

## Support

For support, bug reports, and feature requests:
- **Issue Queue**: [Drupal.org Project Page](https://www.drupal.org/project/accessibility)
- **Documentation**: [Module Documentation](https://www.drupal.org/docs/contributed-modules/accessibility)
- **Community**: [Drupal Slack #accessibility](https://drupal.slack.com/channels/accessibility)

---

*Making the web accessible for everyone, one site at a time.*

