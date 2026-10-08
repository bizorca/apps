# Learn To Be Rich v2 - Refactoring Steps

## Phase 1: Core Framework [CURRENT]
- [x] Create project directory structure
- [x] Document architecture (ARCHITECTURE.md)
- [x] Document refactoring steps (this file)
- [x] Create configuration system
- [x] Create PDO database wrapper
- [x] Create session management
- [x] Create router
- [x] Create view/template engine
- [x] Create front controller (public/index.php)

## Phase 2: Authentication & Account Management
- [x] Create Owner model
- [x] Create AuthController (login, register, logout)
- [x] Create AuthMiddleware
- [x] Create login view (Tailwind)
- [x] Create register view (Tailwind)
- [x] Create AccountController (player list, create, delete)
- [x] Create account dashboard view

## Phase 3: Game Engine Core
- [x] Create Player model
- [x] Create Bank model
- [x] Create EconomyService (interest, inflation, CPI)
- [x] Create JobService (pay, taxes, expenses, raises)
- [x] Create BankService (balance, transactions)
- [x] Create GameEngine (turn processing orchestrator)
- [x] Create GameController

## Phase 4: Real Estate System
- [x] Create RealEstate model
- [x] Create RealEstateService (all status processing, offers, maintenance)
- [x] Create RealEstateController
- [x] Create real estate views (list, details)

## Phase 5: Stock Market System
- [x] Create Stock model
- [x] Create StockService (pricing algorithm, buy/sell, dividends)
- [x] Create StockController
- [x] Create stock views

## Phase 6: Business System
- [x] Create Business model
- [x] Create BusinessService (client growth, advertising, revenue)
- [x] Create BusinessController
- [x] Create business views

## Phase 7: Dream Team System
- [x] Create DreamTeamService (all 11 advisors)
- [x] Create DreamTeamController
- [x] Create dream team views

## Phase 8: UI & Charts
- [x] Create main layout with Tailwind CSS
- [x] Create tabbed game menu
- [x] Create Chart.js integration (replacing Flash SWF)
- [x] Create API endpoints for chart data
- [x] Style all game screens

## Phase 9: Admin Panel
- [x] Create AdminController
- [x] Create admin views (owners, players, logs, tips)

## Phase 10: Database Migration
- [x] Create full SQL migration script
- [x] Create BizDeals seed data script
- [x] Document migration from v1 to v2

## Phase 11: Testing & Polish
- [ ] Test all game mechanics match original
- [ ] Test authentication flow
- [ ] Test all turn actions
- [ ] Cross-browser testing
- [ ] Performance optimization
- [ ] Security audit (SQL injection, XSS, CSRF)

## Migration Notes
- Original database: `ltbrv2db`
- Player tables are dynamically created per player (Bank{ID}, RE{ID}, Stocks{ID}, etc.)
- The BizDeals table contains 391 business types - needs to be seeded
- Flash charts replaced with Chart.js
- register_globals replaced with explicit superglobal access
- All mysql_* calls replaced with PDO prepared statements
- MD5+salt passwords migrated to bcrypt with password_hash()
