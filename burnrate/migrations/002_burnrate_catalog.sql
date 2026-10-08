-- The investment catalog exactly as it stood on burnrate.bizorca.com (15 rows,
-- from the original's migration 004; ids kept). The original repo also has
-- database/seeds/bad_investments.sql with 50 more, which was never loaded on
-- production and is not loaded here either.

INSERT INTO br_badinvestments (InvestmentID, ShortDescription, LongDescription, InitialInvestment, MonthlyFees, VolatilityFactor, BaseReturnRate, CatastropheChance, Category, MinTier) VALUES
('1', 'Blockchain Soup Startup', 'A delivery service that puts QR codes on soup cans and calls it Web3. The whitepaper is mostly emojis.', '2500000.00', '25000.00', '2.50', '-8.00', '12.00', 'startup', '0'),
('2', 'AI Dog Whisperer App', 'Raised $40M to build an app that \"translates\" dog emotions using machine learning. The dog does not consent.', '1500000.00', '18000.00', '3.00', '-10.00', '15.00', 'startup', '0'),
('3', 'Metaverse Golf Course', 'Premium virtual real estate in a golf metaverse nobody asked for. Monthly greens fees in perpetuity.', '5000000.00', '50000.00', '2.00', '-6.00', '10.00', 'startup', '0'),
('4', 'Artisanal Crypto Fund', 'A hedge fund that only invests in coins with animal mascots. Currently heavy in DogeCoin variants.', '10000000.00', '100000.00', '4.00', '-15.00', '20.00', 'crypto', '0'),
('5', 'Celebrity NFT Collection', 'You bought a celebrity-endorsed NFT collection. The celebrity has since pivoted to real estate. The NFTs have not.', '3000000.00', '0.00', '3.50', '-20.00', '25.00', 'nft', '0'),
('6', 'Farm-to-Table Rocket Company', 'Organic, sustainable space tourism. The rockets are biodegradable. Allegedly. The burn rate is not.', '8000000.00', '80000.00', '2.00', '-5.00', '8.00', 'startup', '0'),
('7', 'Offshore Hedge Fund (Cayman)', 'A fund of funds that invests in other funds. Nobody knows what the underlying assets are. That is a feature, not a bug.', '15000000.00', '150000.00', '1.50', '-4.00', '6.00', 'hedge_fund', '0'),
('8', 'Luxury Ice Cube Delivery', 'Hand-carved artisanal ice from a Norwegian glacier, delivered to restaurants by drone. Q3 losses are glacial.', '2000000.00', '20000.00', '2.80', '-12.00', '18.00', 'startup', '0'),
('9', 'VC Fund - Series A Graveyard', 'Diversified exposure to 30 startups that all pivoted to AI between funding rounds. Nine remain operational.', '20000000.00', '200000.00', '3.00', '-8.00', '12.00', 'vc_fund', '0'),
('10', 'Influencer Marketing Agency', 'Manages 200 micro-influencers across 14 platforms. Revenue is measured in \"impressions.\" Expenses are measured in dollars.', '4000000.00', '40000.00', '2.20', '-7.00', '10.00', 'startup', '0'),
('11', 'Sovereign Wealth Fund Slice', 'A fractional interest in a Middle Eastern sovereign wealth fund. Minimum commitment: embarrassing. Monthly fees: not.', '100000000.00', '500000.00', '1.20', '-3.00', '4.00', 'hedge_fund', '256'),
('12', 'Private Equity Buyout — Distressed Luxury', 'Acquire controlling interest in a failing luxury conglomerate. You get a seat on the board. The board meets in Monaco.', '75000000.00', '750000.00', '1.50', '-5.00', '6.00', 'vc_fund', '256'),
('13', 'Dark Pool Trading Algorithm', 'A proprietary quant fund that trades in dark pools using \"undisclosed methodologies.\" The SEC has questions.', '50000000.00', '300000.00', '2.00', '-6.00', '8.00', 'hedge_fund', '256'),
('14', 'Pre-IPO Unicorn Stake', 'Series G investment in a unicorn valued at $48B. The product is a calendar app. With feelings.', '30000000.00', '0.00', '3.50', '-12.00', '20.00', 'startup', '256'),
('15', 'Offshore Crypto Arbitrage Desk', 'A team of 40 quants operating out of a yacht in international waters, exploiting cross-exchange spreads. Probably legal.', '60000000.00', '600000.00', '4.00', '-18.00', '25.00', 'crypto', '256');
