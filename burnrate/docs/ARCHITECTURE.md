# Learn To Be Rich v2 - Architecture Documentation

## Overview
Complete refactor of the Learn To Be Rich economics simulation game from legacy PHP 4/5 to modern PHP 8 with Tailwind CSS, MySQL (PDO), and a clean MVC architecture.

## Directory Structure
```
v2/
├── public/                  # Web root (point Apache/Nginx here)
│   ├── index.php           # Front controller / router entry point
│   ├── css/
│   │   └── app.css         # Tailwind CSS output
│   ├── js/
│   │   └── app.js          # Chart.js + game interactions
│   └── images/             # Static assets
├── config/
│   └── app.php             # Configuration (DB, app settings)
├── database/
│   ├── migrations/
│   │   └── 001_create_tables.sql  # Full schema
│   └── seeds/
│       └── biz_deals.sql   # Business deals seed data
├── src/
│   ├── Core/
│   │   ├── App.php         # Application bootstrap
│   │   ├── Database.php    # PDO database wrapper
│   │   ├── Router.php      # URL routing
│   │   ├── Session.php     # Session management
│   │   ├── View.php        # Template renderer
│   │   └── Helpers.php     # Utility functions
│   ├── Middleware/
│   │   └── AuthMiddleware.php  # Authentication guard
│   ├── Controllers/
│   │   ├── AuthController.php      # Login/Register/Logout
│   │   ├── AccountController.php   # Account & player management
│   │   ├── GameController.php      # Main game loop & menu
│   │   ├── RealEstateController.php # RE actions
│   │   ├── StockController.php     # Stock actions
│   │   ├── BusinessController.php  # Business actions
│   │   ├── DreamTeamController.php # Dream team advisors
│   │   ├── AdminController.php     # Admin panel
│   │   └── ApiController.php       # Chart data JSON endpoints
│   ├── Models/
│   │   ├── Owner.php       # User/Owner model
│   │   ├── Player.php      # Player model (game character)
│   │   ├── Bank.php        # Bank transactions
│   │   ├── RealEstate.php  # Real estate properties
│   │   ├── Stock.php       # Stocks & stock data
│   │   ├── Business.php    # Businesses
│   │   ├── REMarketing.php # RE marketing campaigns
│   │   └── GameLog.php     # Game logging
│   ├── Services/
│   │   ├── GameEngine.php       # Turn processing orchestrator
│   │   ├── EconomyService.php   # Interest rates, inflation, CPI
│   │   ├── RealEstateService.php # RE business logic
│   │   ├── StockService.php     # Stock market simulation
│   │   ├── BusinessService.php  # Business simulation
│   │   ├── DreamTeamService.php # Advisor ratings logic
│   │   ├── BankService.php      # Bank balance & transactions
│   │   └── JobService.php       # Job income, taxes, expenses
│   └── Views/
│       ├── layouts/
│       │   └── main.php         # Main HTML layout with Tailwind
│       ├── auth/
│       │   ├── login.php
│       │   └── register.php
│       ├── account/
│       │   ├── dashboard.php    # Player list
│       │   └── top10.php        # Leaderboard
│       ├── game/
│       │   ├── menu.php         # Main game menu (tabbed)
│       │   ├── summary.php      # Bank balance & transactions
│       │   ├── real_estate.php  # RE portfolio
│       │   ├── re_details.php   # Individual property
│       │   ├── stocks.php       # Stock portfolio
│       │   ├── stock_details.php
│       │   ├── businesses.php   # Business portfolio
│       │   ├── biz_details.php
│       │   ├── dream_team.php   # Advisor ratings
│       │   ├── economy.php      # Economic indicators
│       │   ├── end_game.php     # Game over summary
│       │   └── message.php      # Action result messages
│       ├── admin/
│       │   ├── dashboard.php
│       │   ├── owners.php
│       │   ├── players.php
│       │   └── game_logs.php
│       └── partials/
│           ├── header.php
│           ├── nav.php
│           ├── footer.php
│           └── flash.php
└── storage/
    ├── logs/
    ├── cache/
    └── sessions/
```

## Technology Stack
- **PHP 8.2+**: Typed properties, enums, match expressions, named arguments
- **MySQL 8.0+**: InnoDB, prepared statements via PDO
- **Tailwind CSS 3.x**: Via CDN for simplicity
- **Chart.js 4.x**: Replaces Flash SWF charts
- **Vanilla JS**: No framework needed, Alpine.js for interactivity

## Game Mechanics Preserved
1. **Turn System**: 611 turns (~51 years), one action per turn
2. **Job & Expenses**: Monthly pay, 25% tax, 65% living expenses (reduced by advisors)
3. **Real Estate**: Buy/sell houses, mortgages, rent collection, maintenance, appreciation
4. **Stock Market**: Buy/sell stocks, limit orders, dividends, price fluctuation algorithm
5. **Businesses**: 391 business types, client acquisition, advertising ROI
6. **Dream Team**: 11 advisors with 0-100% ratings that improve gameplay
7. **Economy**: Dynamic interest rates, inflation, CPI, dollar conversion
8. **Banking**: Full transaction ledger with credits/debits/balance

## Key Differences from Legacy
| Legacy | Modern |
|--------|--------|
| mysql_* functions | PDO with prepared statements |
| Global variables | Dependency injection |
| register_globals | Explicit $_GET/$_POST |
| Inline HTML in PHP | Separate view templates |
| Flash SWF charts | Chart.js |
| No routing | Clean URL routing |
| Per-player dynamic tables | Per-player dynamic tables (preserved) |
| MD5 passwords | password_hash() / bcrypt |
| No CSRF protection | CSRF tokens on all forms |

## Database Design
The original uses dynamic per-player tables (Bank{PlayerID}, RE{PlayerID}, etc.).
This pattern is preserved for backwards compatibility but wrapped in clean models.

## Authentication Flow
1. Register → Create Owner → Email confirmation (optional)
2. Login → Session with bcrypt password verification
3. Select/Create Player → Game begins
4. Each page checks AuthMiddleware → redirects if not logged in
