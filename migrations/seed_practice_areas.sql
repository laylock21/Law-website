-- Seed Practice Areas Table
-- This file contains sample practice areas data
-- Run this if your practice_areas table is empty

-- Insert sample practice areas
INSERT INTO practice_areas (area_name, description, is_active) VALUES
('Criminal Defense', 'Aggressive defense strategies and courtroom expertise to protect your rights and freedom.', 1),
('Family Law', 'Compassionate guidance through divorce, custody, and family matters with focus on resolution.', 1),
('Corporate Law', 'Strategic business counsel, contract negotiation, and corporate governance for growing companies.', 1),
('Real Estate Law', 'Comprehensive real estate services from transactions to development and planning strategies.', 1),
('Health Care Law', 'Navigating complex healthcare regulations, compliance, and medical practice legal matters.', 1),
('Educational Law', 'Specialized legal services for educational institutions, compliance, and student rights.', 1),
('Immigration Law', 'Expert guidance through visa applications, citizenship, and immigration proceedings.', 1),
('Employment Law', 'Protecting employee rights and advising employers on workplace compliance and disputes.', 1),
('Tax Law', 'Strategic tax planning, compliance, and representation in tax disputes and audits.', 1),
('Intellectual Property', 'Protecting your innovations through patents, trademarks, copyrights, and trade secrets.', 1)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    is_active = VALUES(is_active);
