# Learn To Be Rich - Game Mechanics Documentation

## Overview
Learn To Be Rich is a turn-based financial simulation game where players manage a 51-year (611-turn) career building wealth through real estate, stocks, businesses, and career advancement.

## Turn System
- Each turn represents one month of game time
- Maximum: 611 turns (~51 years)
- One major action per turn (plus automatic processing)

## Turn Processing Order
Each turn processes in this order:
1. **Job & Expenses** - Income, taxes, living expenses, bank interest
2. **Economy** - Interest rate, inflation, CPI updates
3. **Real Estate** - Rent collection, PITI, appreciation, maintenance
4. **Businesses** - Client gain/loss, revenue, expenses
5. **Stocks** - Price fluctuations, dividends, limit orders
6. **Game Logging** - Snapshot of net worth and assets

## Job & Expenses
- **Job Pay**: Monthly salary credited to bank
- **Income Tax**: 25% of job income
- **Living Expenses**: 65% of income, reduced by:
  - Financial Planner Rating: up to 10% discount
  - Accountant Rating: up to 10% discount
- **Bank Interest**: (0.35 * InterestRate / 1200) * Balance
- **Raises**: Every 24 turns, 1-2x inflation rate increase

## Real Estate
### Properties
- Randomly generated with beds, baths, location
- Values scaled by inflation (OneDollar multiplier)
- Status codes: 1=MLS, 21=FSBO, 10=Owned, 98=Sold

### Purchasing
- Down payment: 0-20% (based on Mortgage Broker rating)
- 30-year fixed rate mortgage
- Monthly payment = P * (r(1+r)^360) / ((1+r)^360 - 1)

### Monthly Costs (Owned Properties)
- Mortgage payment (PITI)
- Property taxes: (TaxRate/100 * Value) / 12
- Insurance: (0.01 - 0.005 * InsuranceAgentRating/100) * Value / 12
- Maintenance: Based on condition ratings

### Appreciation
- Monthly: AppreciationRate / 12 / 100 of current value
- Rate varies near inflation with random variation

### Condition Ratings (0-100)
- Roof, Kitchen, Bathrooms, Flooring, Paint, Major Systems
- Degrade slightly each turn (0-0.3%)
- Lower ratings = higher maintenance costs

## Stock Market
### Price Algorithm (per turn)
1. Base roll: rand(30, 140)
2. Industry bonus: rand(-20, 20)
3. Mean reversion: +10 if below 25th percentile, -20 if above 75th
4. Interest rate sensitivity: effect based on rate vs. baseline
5. Price change = (totalFactor - 85) / 1000 * currentPrice

### Trading
- Buy/sell at market price
- Commission: OneDollar * 20 - 0.05
- Limit orders: auto-execute when price hits target
- Dividends: quarterly, can reinvest or cash out

### Stock Broker Discount
- Purchase prices reduced up to 10% based on StockBrokerRating

## Businesses
### Client Growth Formula
- **Clients Lost**: NumberClients * (PercentClientsLostPerTurn / 100)
- **Clients Gained**: MaxClients * (1 - e^(-AdEffectiveness * MarketingBonus * AdSpend/OneDollar))
- **Revenue**: Clients * RevenuePerClient * OneDollar
- **Expenses**: ExpensesPerTurn * OneDollar + AdSpend

### Random Events
- ~1% chance: Max clients adjusts
- ~3% chance: 10-30% client loss
- ~2% chance: 5-20 client gain

### Business Broker Discount
- Startup costs reduced up to 15% based on BusinessBrokerRating

## Dream Team (11 Advisors)
Each advisor has a 0-100% rating that improves gameplay:

| Advisor | Effect |
|---------|--------|
| Real Estate Agent | Better property deals |
| Property Manager | Lower vacancy rates (10% → 2%) |
| Mentor | Improves all other upgrade speeds |
| Mortgage Broker | Higher LTV (80% → 100%), better pre-qual |
| Accountant | Reduces living expenses (up to 10% of income) |
| Attorney | Legal protection |
| Stock Broker | Up to 10% discount on stock purchases |
| Business Broker | Up to 15% discount on business startup |
| Insurance Agent | Halves insurance costs |
| Personal Dev Coach | Accelerates all advisor upgrades |
| Financial Planner | Reduces living expenses (up to 10% of income) |
| Marketing Consultant | Boosts advertising effectiveness |

### Upgrade Formula
- Base improvement: rand(1,10) + rand(1,5)
- PDC bonus: * (1 + PersonalDevelopmentCoachRating/100)
- Mentor bonus: + (MentorRating/100) * 5

## Economy
- **Interest Rate**: 2-10%, changes ±0.5% per turn
- **Inflation Rate**: 0.5-8%, changes ±0.3% per turn
- **CPI**: Tracks cumulative inflation from base 100
- **OneDollar**: Cumulative inflation multiplier (all prices scale)

## Net Worth Calculation
NetWorth = BankBalance + RE_Equity + StockValue
- RE_Equity = Sum(CurrentValue - LoanBalance) for owned properties
- StockValue = Sum(Shares * Price) for all holdings
