-- Burn Rate, ported from burnrate.bizorca.com (MySQL, database/migrations
-- 001-004 of the original).
--
-- What changed from the original schema:
--   * Owners -> br_owners, keyed on the shared users.id. Email, name and
--     password live in users; the legacy billing columns (credit card, PayPal,
--     AWeber, referral flags) and the empty SignUps, PasswordResets,
--     TransactionsLog, MembershipBilling, PayPalTransactionLog,
--     LTBRMemberBillingLog and UserPermissions tables did not come across.
--     IsAdmin is new: admin is no longer "MemberLevel >= 65536" (see
--     src/Middleware/AdminMiddleware.php).
--   * The six tables the original created PER PLAYER at game start
--     (Bank{id}, RE{id}, Stocks{id}, StockData{id}, Businesses{id},
--     REMarketing{id}) are single tables with a PlayerID column, each
--     cascading from br_players, so deleting a player (or an account) removes
--     everything. Column names and types are unchanged, UNSIGNED DECIMALs
--     included, so values round and reject exactly as before.
--   * br_gamelog now cascades from br_players (the original left orphans).
--   * site_settings -> br_site_settings; the Stripe key rows are gone (keys
--     come from .env.php). The duplicate CamelCase SiteSettings table is gone.

CREATE TABLE IF NOT EXISTS br_owners (
    OwnerID         INT NOT NULL PRIMARY KEY,
    MemberLevel     INT UNSIGNED NOT NULL DEFAULT 0,
    RoundsCompleted INT UNSIGNED NOT NULL DEFAULT 0,
    SignUpDate      DATETIME DEFAULT NULL,
    LastLoginDate   DATETIME DEFAULT NULL,
    IsAdmin         TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_br_owners_user FOREIGN KEY (OwnerID) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS br_players (
    PlayerID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    OwnerID INT NOT NULL,
    PlayerName VARCHAR(100) NOT NULL DEFAULT '',
    CreatedDate DATETIME DEFAULT NULL,
    LastPlayedDate DATETIME DEFAULT NULL,
    Turn INT UNSIGNED NOT NULL DEFAULT 1,
    TurnAction INT UNSIGNED NOT NULL DEFAULT 0,
    TurnProcessedThrough INT UNSIGNED NOT NULL DEFAULT 0,
    ShowTab VARCHAR(20) NOT NULL DEFAULT 'summary',
    VanityIncome DECIMAL(15,2) NOT NULL DEFAULT 2500.00,
    VanityExpenses DECIMAL(15,2) NOT NULL DEFAULT 15000.00,
    LifestyleBurn DECIMAL(15,2) NOT NULL DEFAULT 500000.00,
    BankruptTurn INT UNSIGNED NOT NULL DEFAULT 0,
    InterestRate DECIMAL(5,2) NOT NULL DEFAULT 6.00,
    InflationRate DECIMAL(5,2) NOT NULL DEFAULT 4.00,
    DollarConversion DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    ConsumerPriceIndex DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    OneDollar DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    StockMarketRate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PersonalShopperRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PartyPlannerRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    YesManRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    CelebrityChefRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    FashionConsultantRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ArtDealerRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    InteriorDesignerRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    TravelAgentRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    SocialMediaManagerRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    LifeCoachRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    AstrologerRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ConciergeRating DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ActiveCrisis VARCHAR(500) NOT NULL DEFAULT '',
    CrisisTurnsRemaining INT UNSIGNED NOT NULL DEFAULT 0,
    GameLogProcessedThrough INT UNSIGNED NOT NULL DEFAULT 0,
    UpgradeGranted TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_br_players_owner (OwnerID),
    KEY idx_br_players_bankrupt (BankruptTurn),
    CONSTRAINT fk_br_players_owner FOREIGN KEY (OwnerID) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS br_gamelog (
    GameLogID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    PlayerID INT UNSIGNED NOT NULL DEFAULT 0,
    BankBalance DECIMAL(15,2) NOT NULL DEFAULT 0,
    IABankBalance DECIMAL(15,2) NOT NULL DEFAULT 0,
    OneDollar DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    WeekInRealTime INT UNSIGNED NOT NULL DEFAULT 0,
    YearInRealTime INT UNSIGNED NOT NULL DEFAULT 0,
    NumProperties INT UNSIGNED NOT NULL DEFAULT 0,
    NumToys INT UNSIGNED NOT NULL DEFAULT 0,
    NumInvestments INT UNSIGNED NOT NULL DEFAULT 0,
    NetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    IANetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    PropertyNetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    PropertyIANetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    ToyNetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    ToyIANetWorth DECIMAL(15,2) NOT NULL DEFAULT 0,
    StockMarketRate DECIMAL(5,2) NOT NULL DEFAULT 0,
    InterestRate DECIMAL(5,2) NOT NULL DEFAULT 0,
    InflationRate DECIMAL(5,2) NOT NULL DEFAULT 0,
    KEY idx_br_gamelog_player_turn (PlayerID, Turn),
    CONSTRAINT fk_br_gamelog_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The catalog of terrible investments (seeded by 002).
CREATE TABLE IF NOT EXISTS br_badinvestments (
    InvestmentID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ShortDescription VARCHAR(200) NOT NULL DEFAULT '',
    LongDescription TEXT,
    InitialInvestment DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    MonthlyFees DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    VolatilityFactor DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 1.00,
    BaseReturnRate DECIMAL(5,2) NOT NULL DEFAULT -5.00,
    CatastropheChance DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 5.00,
    Category VARCHAR(50) NOT NULL DEFAULT 'startup',
    MinTier INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was Bank{PlayerID}: the running ledger. Balance is the last row's Balance.
CREATE TABLE IF NOT EXISTS br_bank (
    BankID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    Description VARCHAR(200) DEFAULT '',
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    EntryType VARCHAR(10) NOT NULL DEFAULT '',
    RecordID INT UNSIGNED NOT NULL DEFAULT 0,
    Credit DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    Debit DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    Balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    KEY idx_br_bank_player (PlayerID, BankID),
    KEY idx_br_bank_player_turn (PlayerID, Turn),
    CONSTRAINT fk_br_bank_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was RE{PlayerID}: mansions, islands, penthouses.
CREATE TABLE IF NOT EXISTS br_re (
    REID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    Description VARCHAR(200) DEFAULT '',
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    PurchasePrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    PurchaseTurn INT UNSIGNED NOT NULL DEFAULT 0,
    LoanBalance DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    RentalRate DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    SoldTurn INT UNSIGNED NOT NULL DEFAULT 0,
    CurrentValue DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    MinPayment DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    CurrentPayment DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    InterestRate DECIMAL(4,2) UNSIGNED NOT NULL DEFAULT 0,
    LoanType INT UNSIGNED NOT NULL DEFAULT 0,
    Status INT UNSIGNED NOT NULL DEFAULT 0,
    AppreciationRate DECIMAL(4,2) NOT NULL DEFAULT 0,
    AskingPrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    LowComp DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    HighComp DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    LowRental DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    HighRental DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    TaxRate DECIMAL(4,2) NOT NULL DEFAULT 0,
    CashFlow DECIMAL(15,2) NOT NULL DEFAULT 0,
    SoldPrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    RoofRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    KitchenRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    BathroomsRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    FlooringRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    PaintRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    MajorSystemsRating DECIMAL(4,1) UNSIGNED NOT NULL DEFAULT 0,
    RepairStatus INT UNSIGNED NOT NULL DEFAULT 0,
    StaffCost DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    PropertyType VARCHAR(50) NOT NULL DEFAULT 'mansion',
    KEY idx_br_re_player (PlayerID, Status),
    CONSTRAINT fk_br_re_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was Stocks{PlayerID}: toys (supercars, yachts, jets, art). Named for the
-- stock market of the game it was forked from.
CREATE TABLE IF NOT EXISTS br_stocks (
    StockID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    StockSymbol VARCHAR(50) DEFAULT '',
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    IndustryID INT UNSIGNED NOT NULL DEFAULT 0,
    NumberShares INT UNSIGNED NOT NULL DEFAULT 0,
    AllTimeHigh DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    AllTimeLow DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    StockPrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    PurchasePrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    RateOfReturn DECIMAL(5,2) NOT NULL DEFAULT 0,
    InterestRateSensitivity INT UNSIGNED NOT NULL DEFAULT 0,
    EarningsPerShare DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    Dividends DECIMAL(15,2) NOT NULL DEFAULT 0,
    ReinvestDividends CHAR(1) NOT NULL DEFAULT 'N',
    DividendTurn INT UNSIGNED NOT NULL DEFAULT 0,
    StockPriceChange DECIMAL(15,2) NOT NULL DEFAULT 0,
    OrderType INT UNSIGNED NOT NULL DEFAULT 0,
    OrderPrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    OrderQuantity INT UNSIGNED NOT NULL DEFAULT 0,
    MonthlyCost DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    ToyCategory VARCHAR(50) NOT NULL DEFAULT 'supercar',
    KEY idx_br_stocks_player (PlayerID, StockID),
    CONSTRAINT fk_br_stocks_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was StockData{PlayerID}: toy value history.
CREATE TABLE IF NOT EXISTS br_stockdata (
    StockDataID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    StockID INT UNSIGNED NOT NULL DEFAULT 0,
    StockPrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_br_stockdata_player_stock (PlayerID, StockID, Turn),
    CONSTRAINT fk_br_stockdata_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was Businesses{PlayerID}: the player's bad investments.
CREATE TABLE IF NOT EXISTS br_businesses (
    BusinessID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    ShortDescription VARCHAR(200) DEFAULT '',
    LongDescription TEXT,
    PurchasePrice DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    PurchaseTurn INT UNSIGNED NOT NULL DEFAULT 0,
    StartupCost DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    ExpensesPerTurn DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    RevenuePerClientPerTurn DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    AdvertisingPerTurn DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    AdvertisingEffectiveness DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0,
    NumberClients INT UNSIGNED NOT NULL DEFAULT 0,
    MaxNumberClients INT UNSIGNED NOT NULL DEFAULT 0,
    PercentClientsLostPerTurn DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0,
    CurrentValue DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    Category VARCHAR(50) NOT NULL DEFAULT 'startup',
    KEY idx_br_businesses_player (PlayerID, BusinessID),
    CONSTRAINT fk_br_businesses_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was REMarketing{PlayerID}. Nothing writes to it (kept "for compatibility"
-- in the original too); the model still reads it.
CREATE TABLE IF NOT EXISTS br_remarketing (
    REMarketingID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    PlayerID INT UNSIGNED NOT NULL,
    Turn INT UNSIGNED NOT NULL DEFAULT 0,
    CostPerTurn DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0,
    AdEffectiveness DECIMAL(5,5) UNSIGNED NOT NULL DEFAULT 0,
    MinBuyAmount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    ShortDesc VARCHAR(200) DEFAULT '',
    MaxNumLeadsPerTurn INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_br_remarketing_player (PlayerID),
    CONSTRAINT fk_br_remarketing_player FOREIGN KEY (PlayerID) REFERENCES br_players (PlayerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS br_site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO br_site_settings (setting_key, setting_value) VALUES
    ('registration_open', '1'),
    ('stripe_enabled', '0'),
    ('site_tagline', 'Master the Art of Going Broke');
