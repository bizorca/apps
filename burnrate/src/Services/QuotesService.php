<?php

namespace App\Services;

class QuotesService
{
    /**
     * Anti-motivational quotes organized by context.
     * For the game "Burn Rate" -- where going broke is the goal.
     */
    private const QUOTES = [

        // ---------------------------------------------------------------
        // PURCHASE  -- the thrill of buying things you don't need
        // ---------------------------------------------------------------
        'purchase' => [
            "A penny saved is a penny that could've been on fire.",
            "Retail therapy is just therapy with better accessories.",
            "Buy the dip. Buy the peak. Buy whatever's in between. Just buy.",
            "Money can't buy happiness, but it can buy a yacht, and have you ever seen someone frown on a yacht?",
            "The best things in life are free. The second best are ruinously expensive.",
            "Treat yourself. You deserve it. You always deserve it. Never stop deserving it.",
            "A fool and his money are soon partying.",
            "If you have to ask the price, you're not spending recklessly enough.",
            "Impulse buying is just your subconscious being financially decisive.",
            "Why comparison shop when you can just buy both?",
            "Shopping is cardio for your credit card.",
            "Every purchase is an investment in your emotional well-being. Don't let math ruin this.",
            "Buy now, think never.",
            "The only thing worse than buyer's remorse is buyer's restraint.",
            "You can't take it with you, so why wait until you're dead to get rid of it?",
        ],

        // ---------------------------------------------------------------
        // INVESTMENT  -- the art of turning money into less money
        // ---------------------------------------------------------------
        'investment' => [
            "Diversify your losses across multiple asset classes.",
            "Buy high, sell low, blame your astrologer.",
            "The best time to invest was never. The second best time is also never.",
            "Compound interest is the eighth wonder of the world. Compound spending is the ninth.",
            "Time in the market beats timing the market, but neither beats leaving the market entirely.",
            "Be fearful when others are greedy. Be greedy when others are fearful. Actually, just be greedy.",
            "Past performance is no guarantee of future results. Past losses, however, are practically a promise.",
            "Dollar-cost averaging is just losing money on a schedule.",
            "An index fund is a diversified way to watch everything decline simultaneously.",
            "I don't always invest, but when I do, I prefer things I don't understand.",
            "Buy the rumor, sell the news, regret both decisions equally.",
            "Risk and reward are related. So are risk and catastrophe.",
            "The stock market is a device for transferring money from the impatient to the also impatient.",
            "Hedge funds are just mutual funds with better marketing and worse returns.",
            "Your portfolio should be like your life: chaotic, overleveraged, and full of surprises.",
        ],

        // ---------------------------------------------------------------
        // PARTY  -- celebrating the act of hemorrhaging wealth
        // ---------------------------------------------------------------
        'party' => [
            "You can't put a price on a good time. But if you could, it would be way too much.",
            "Life is short. Your bar tab shouldn't be.",
            "Champagne wishes and caviar nightmares.",
            "Nothing says 'I've made it' like an ice sculpture that melts in two hours.",
            "Party like it's 1999 -- back when the money still seemed real.",
            "The only thing better than a party is a party you can't afford.",
            "DJ fees are just sound investments. Literally.",
            "Why throw a party when you can throw a gala? Why throw a gala when you can throw a festival?",
            "A good host never lets their guests see the bill. A great host never sees it either.",
            "Open bars are just democracy applied to alcohol.",
            "Fireworks: the most beautiful way to set money on fire since cryptocurrency.",
            "Catering is just paying someone else to feed your poor decisions.",
            "There are two types of parties: forgettable ones, and ones that show up in bankruptcy filings.",
            "The after-party is where the real financial damage happens.",
        ],

        // ---------------------------------------------------------------
        // BANKRUPTCY  -- the finish line
        // ---------------------------------------------------------------
        'bankruptcy' => [
            "Bankruptcy isn't the end. It's the beginning of not having to worry about money.",
            "They say money can't buy happiness. Bankruptcy can't either, but at least it's free.",
            "Going broke is just aggressive minimalism.",
            "Behind every great fortune is an even greater fortune that was squandered.",
            "Bankruptcy is just a factory reset for your finances.",
            "The road to ruin is paved with receipts.",
            "You haven't truly lived until your net worth is a punchline.",
            "Zero is the new million.",
            "Financial freedom is having nothing left to lose.",
            "If at first you don't go broke, try, try again.",
            "Debt is just money's way of saying 'I'll miss you.'",
            "You can't go bankrupt if you never had a plan. Wait, actually you can. That's the fastest way.",
            "Chapter 11 is just the universe's way of giving you a fresh start.",
            "The only thing more impressive than building a fortune is dismantling one.",
            "Rock bottom has a basement. And even the basement has a subfloor. Keep digging.",
        ],

        // ---------------------------------------------------------------
        // CRISIS  -- when things go beautifully wrong
        // ---------------------------------------------------------------
        'crisis' => [
            "Every cloud has a silver lining. Sell the silver.",
            "When life gives you lemons, leverage the lemons, short the lemonade, and file for citrus bankruptcy.",
            "This too shall pass. Your money, specifically. Out of your account.",
            "A crisis is just an opportunity wearing a very convincing disguise.",
            "Markets crash. Empires fall. Your net worth was just following tradition.",
            "The house always wins. Unless it's your house. Then the bank wins.",
            "Keep calm and keep hemorrhaging capital.",
            "In times of economic uncertainty, the only certainty is that you'll have less money.",
            "When the going gets tough, the tough get a second mortgage.",
            "Every financial crisis is just capitalism's way of redistributing your wealth.",
            "Panic selling is just decisive action with extra adrenaline.",
            "The market will recover. Your portfolio is another story.",
            "A recession is when your neighbor loses his money. A depression is when you do. A party is when nobody has any.",
            "Adversity builds character. Insolvency builds great anecdotes.",
        ],

        // ---------------------------------------------------------------
        // ENTOURAGE  -- the people who help you spend
        // ---------------------------------------------------------------
        'entourage' => [
            "Surround yourself with people who believe in your vision of having no money.",
            "A good accountant finds deductions. A great accountant finds nothing left to deduct.",
            "Your network is your net worth. And your net worth is plummeting, so thanks, network.",
            "Behind every bankrupt person is a supportive team of enablers.",
            "Hire slow, fire never. Payroll is the gift that keeps on giving.",
            "A personal chef is just meal prep for people who respect themselves too much.",
            "Loyalty can't be bought. But everything else can, and your entourage is here to help.",
            "The best financial advisor is the one who tells you what you want to hear.",
            "A butler is a lifestyle expense. A second butler is a lifestyle choice. A third is a lifestyle.",
            "Every billionaire needs a hype man. Every former billionaire needed two.",
            "Your entourage doesn't cost money. It costs ALL the money.",
            "A good lawyer gets you out of trouble. A great lawyer gets you into better trouble.",
            "Assistants are just subscriptions to having your life managed by someone more organized than you.",
            "Friends are the family you choose. Entourage members are the family you invoice.",
        ],

        // ---------------------------------------------------------------
        // PROPERTY  -- real estate misadventures
        // ---------------------------------------------------------------
        'property' => [
            "Location, location, depreciation.",
            "They're not making any more land. So buy all of it.",
            "Real estate: where your money goes to sit perfectly still and lose value creatively.",
            "A house is not a home. It's a money pit with curtains.",
            "Buy land -- God isn't making more of it. But foreclosure courts are.",
            "The three rules of real estate: overpay, over-renovate, overreact.",
            "Home is where the mortgage is.",
            "An empty mansion is just a really expensive echo chamber.",
            "Renovation budgets are just opening offers in a negotiation with chaos.",
            "Nothing says 'I've arrived' like property taxes you can't afford.",
            "A swimming pool adds value to your home and subtracts it from your bank account, permanently.",
            "Square footage is just a measurement of how much space your regret can fill.",
            "Curb appeal is the real estate term for 'looks great, costs everything.'",
            "Why rent when you can own all the problems yourself?",
        ],

        // ---------------------------------------------------------------
        // TOY  -- expensive toys and ridiculous luxuries
        // ---------------------------------------------------------------
        'toy' => [
            "Cash is king. So spend it like royalty.",
            "A yacht is just a boat that went to private school.",
            "The difference between a toy and an investment is how much you smile while losing money.",
            "Sports cars depreciate 30% the moment you drive off the lot. So buy two and depreciate 60%.",
            "A private jet is just a taxi with better PR.",
            "If your watch costs more than your car, you're doing it right. If your car costs more than your house, even better.",
            "Luxury is the only comfort that money can buy. Everything else is just furniture.",
            "You don't need another supercar. But 'need' is such a limiting word.",
            "A gold-plated anything is automatically a better anything.",
            "The only toy not worth buying is the one you already own.",
            "Collectibles appreciate in value. Yours won't, but that's not really the point.",
            "Every billionaire has a helicopter. Every interesting billionaire has crashed one.",
            "A wine cellar is just a savings account that gets you drunk.",
            "Art is an investment if it goes up in value. It's interior design if it doesn't. Either way, buy more.",
        ],

        // ---------------------------------------------------------------
        // GENERAL  -- universal wisdom for the aspiring bankrupt
        // ---------------------------------------------------------------
        'general' => [
            "Rule #1: Never lose money. Rule #2: See that's boring, let's lose money.",
            "Fortune favors the bold. Bankruptcy favors the bolder.",
            "Money talks. Mine just says goodbye.",
            "A budget is just a suggestion you make to yourself and then politely ignore.",
            "Financial literacy is knowing exactly how you're ruining yourself.",
            "The journey of a thousand debts begins with a single swipe.",
            "Live below your means? What is this, a game for cowards?",
            "YOLO is an investment strategy if you're brave enough.",
            "Wealth is not about how much you earn. It's about how spectacularly you spend.",
            "Save for a rainy day? In this economy, every day is a rainy day. Might as well buy an umbrella factory.",
            "The early bird gets the worm. The late bird gets bottle service.",
            "Frugality is the refuge of the unimaginative.",
            "Think outside the budget.",
            "What's the point of FU money if you never say FU?",
            "The rich get richer. But the broke get better stories.",
            "Life is a balance sheet, and mine is abstract art.",
            "Not all who wander are lost. Some are just looking for the nearest luxury outlet.",
            "Money is the root of all evil. Spending it is the tree, the branches, and the leaves.",
            "If you think nobody cares about you, try missing a credit card payment.",
            "There are two kinds of people: those who save and those who actually enjoy their lives.",
        ],
    ];

    /**
     * Return a random quote from any context.
     */
    public function getRandomQuote(): string
    {
        $all = array_merge(...array_values(self::QUOTES));
        return $all[array_rand($all)];
    }

    /**
     * Return a quote appropriate for the given gameplay context.
     *
     * Supported contexts: purchase, investment, party, bankruptcy, crisis,
     *                     entourage, property, toy, general
     *
     * Falls back to a random quote from any context if the context is unknown.
     */
    public function getQuoteForContext(string $context): string
    {
        $context = strtolower(trim($context));

        if (isset(self::QUOTES[$context])) {
            $pool = self::QUOTES[$context];
            return $pool[array_rand($pool)];
        }

        // Unknown context -- return any random quote
        return $this->getRandomQuote();
    }

    /**
     * Return every quote for a given context (useful for UI carousels, etc.).
     *
     * @return string[]
     */
    public function getAllQuotesForContext(string $context): array
    {
        $context = strtolower(trim($context));
        return self::QUOTES[$context] ?? [];
    }

    /**
     * Return the complete list of available contexts.
     *
     * @return string[]
     */
    public function getAvailableContexts(): array
    {
        return array_keys(self::QUOTES);
    }

    /**
     * Return the total number of quotes across all contexts.
     */
    public function getTotalQuoteCount(): int
    {
        $count = 0;
        foreach (self::QUOTES as $quotes) {
            $count += count($quotes);
        }
        return $count;
    }
}
