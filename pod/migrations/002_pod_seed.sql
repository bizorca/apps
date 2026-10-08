-- The original's default forum categories (also what the live database held).
-- bin/import-mysqldump.php replaces these with the dump's own rows and ids.
INSERT IGNORE INTO pd_forum_categories (name, slug, description, sort_order) VALUES
    ('General Discussion', 'general', 'General community chat', 1),
    ('Business Coaching', 'business-coaching', 'Strategy, growth, and business questions', 2),
    ('Tech & Tools', 'tech-and-tools', 'Software, tools, and workflows', 3),
    ('Wins & Celebrations', 'wins', 'Share your wins here', 4);
