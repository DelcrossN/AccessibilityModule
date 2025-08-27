-- Sample data for testing the accessibility reports interface
-- This will add some test reports so you can see the new UI in action

-- Insert sample reports
INSERT INTO accessibility_reports (url, title, violation_count, critical_count, serious_count, moderate_count, minor_count, last_scanned) VALUES
('https://my-drupal10-site.ddev.site/', 'Homepage', 14, 3, 4, 5, 2, UNIX_TIMESTAMP()),
('https://my-drupal10-site.ddev.site/about', 'About Us', 8, 2, 1, 3, 2, UNIX_TIMESTAMP() - 3600),
('https://my-drupal10-site.ddev.site/contact', 'Contact Page', 12, 1, 3, 6, 2, UNIX_TIMESTAMP() - 7200),
('https://my-drupal10-site.ddev.site/services', 'Services', 6, 0, 2, 3, 1, UNIX_TIMESTAMP() - 10800),
('https://my-drupal10-site.ddev.site/blog', 'Blog', 9, 1, 2, 4, 2, UNIX_TIMESTAMP() - 14400);

-- Insert corresponding violation details for the homepage
INSERT INTO accessibility_violations (url, rule_id, impact, impact_weight, description, help, help_url, tags, nodes_count, nodes_data) VALUES
('https://my-drupal10-site.ddev.site/', 'aria-allowed-attr', 'critical', 1, 'Ensures an element''s role supports its ARIA attributes', 'Elements must only use allowed ARIA attributes', 'https://dequeuniversity.com/rules/axe/4.4/aria-allowed-attr', '["cat.aria", "wcag2a", "wcag412"]', 2, '[]'),
('https://my-drupal10-site.ddev.site/', 'button-name', 'critical', 1, 'Ensures buttons have discernible text', 'Buttons must have discernible text', 'https://dequeuniversity.com/rules/axe/4.4/button-name', '["cat.name-role-value", "wcag2a", "wcag412"]', 3, '[]'),
('https://my-drupal10-site.ddev.site/', 'image-alt', 'critical', 1, 'Ensures <img> elements have alternate text or a role of none or presentation', 'Images must have alternate text', 'https://dequeuniversity.com/rules/axe/4.4/image-alt', '["cat.text-alternatives", "wcag2a", "wcag111"]', 1, '[]');
