-- Reference data the app depends on: launch cities and partner restaurants,
-- copied from the live placecard.bizorca.com database on 2026-10-08. The iOS
-- app's MockDataService hardcodes these restaurant ids (cm-r1..cm-r5, pt-r1..pt-r5).

INSERT IGNORE INTO pc_cities (id, name, country, emoji, tagline) VALUES
('chiang-mai', 'Chiang Mai', 'Thailand', '🇹🇭', 'Northern Thai culture meets world-class food'),
('port-townsend', 'Port Townsend', 'United States', '🇺🇸', 'Victorian seaport with a serious local food scene');

INSERT IGNORE INTO pc_restaurants (id, name, city_id, cuisine, neighborhood, description, price_range, is_active) VALUES
('cm-r1', 'Huen Phen', 'chiang-mai', 'Northern Thai (Lanna)', 'Phra Sing, Old City', 'A 40-year Chiang Mai institution on Ratchamanka Road. Family kitchen, generations of Lanna recipes, tables that fill up fast.', '$', 1),
('cm-r2', 'The House by Ginger', 'chiang-mai', 'Contemporary Northern Thai', 'Old City', 'Michelin-recognized colonial mansion with Northern Thai comfort food and a modern eye — plus a cocktail bar worth staying for.', '$$', 1),
('cm-r3', 'Baan Landai', 'chiang-mai', 'Northern Thai', 'Old Town', 'Michelin Bib Gourmand winner. Home-style Northern Thai in a heritage house. The kind of meal you describe to people for years.', '$$', 1),
('cm-r4', 'Paak Dang Riverside', 'chiang-mai', 'Thai Seafood & Barbecue', 'Ping River', 'Michelin Plate riverside spot known for jumbo river prawns and views of the Ping that make dinner feel like an occasion.', '$$', 1),
('cm-r5', 'Blackitch Artisan Kitchen', 'chiang-mai', 'Modern Thai / Chef''s Table', 'Nimman', 'Michelin-listed chef''s table. A 10-course tasting menu built around seasonal, foraged ingredients. No two dinners are the same.', '$$$', 1),
('pt-r1', 'Fountain Cafe', 'port-townsend', 'New American / Seafood', 'Downtown', 'Port Townsend''s oldest continuously running restaurant — 40+ years, 28 seats, locally sourced everything. Reserve ahead on weekends.', '$$', 1),
('pt-r2', 'Alchemy Bistro & Wine Bar', 'port-townsend', 'American / Small Plates', 'Downtown', 'Old-world bistro with sophisticated small plates, local oysters, prime steak, and a wine list that earns its shelf space.', '$$', 1),
('pt-r3', 'Finistère', 'port-townsend', 'New American / Pacific Northwest', 'Uptown', 'Brittany-inspired farm-to-table with a serious Pacific Northwest seafood focus — the closest thing PT has to a destination dining experience.', '$$$', 1),
('pt-r4', 'Sirens Pub', 'port-townsend', 'American Gastropub', 'Downtown Waterfront', 'Lively waterfront pub with solid burgers, flatbreads, and views. The natural call for a casual group that wants a social atmosphere.', '$', 1),
('pt-r5', 'Silverwater Cafe', 'port-townsend', 'Pacific Northwest Seafood', 'Uptown', 'Fresh local seafood, warm room, casually refined. A long-standing PT favorite that makes regulars out of first-timers.', '$$', 1);
