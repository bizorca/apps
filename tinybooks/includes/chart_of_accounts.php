<?php
// Default chart of accounts for a service-based business
// Works for for-profit and non-profit (equity labels differ)

function default_chart_of_accounts(string $org_type = 'for_profit'): array {
    $equity_label = $org_type === 'non_profit' ? 'Net Assets' : "Owner's Equity";
    $retained_label = $org_type === 'non_profit' ? 'Retained Surplus' : 'Retained Earnings';

    return [
        // ASSETS
        ['number' => '1000', 'name' => 'Checking Account',      'type' => 'asset',     'subtype' => 'bank'],
        ['number' => '1010', 'name' => 'Savings Account',       'type' => 'asset',     'subtype' => 'bank'],
        ['number' => '1020', 'name' => 'Petty Cash',            'type' => 'asset',     'subtype' => 'cash'],
        ['number' => '1100', 'name' => 'Accounts Receivable',   'type' => 'asset',     'subtype' => 'receivable'],
        ['number' => '1200', 'name' => 'Prepaid Expenses',      'type' => 'asset',     'subtype' => 'prepaid'],
        ['number' => '1300', 'name' => 'Security Deposits',     'type' => 'asset',     'subtype' => 'other'],
        ['number' => '1500', 'name' => 'Equipment',             'type' => 'asset',     'subtype' => 'fixed'],
        ['number' => '1510', 'name' => 'Accumulated Depreciation', 'type' => 'asset',  'subtype' => 'fixed'],

        // LIABILITIES
        ['number' => '2000', 'name' => 'Accounts Payable',      'type' => 'liability', 'subtype' => 'payable'],
        ['number' => '2100', 'name' => 'Credit Card',           'type' => 'liability', 'subtype' => 'credit_card'],
        ['number' => '2200', 'name' => 'Sales Tax Payable',     'type' => 'liability', 'subtype' => 'tax'],
        ['number' => '2300', 'name' => 'Payroll Liabilities',   'type' => 'liability', 'subtype' => 'payroll'],
        ['number' => '2400', 'name' => 'Gift Cards / Prepaid',  'type' => 'liability', 'subtype' => 'deferred'],
        ['number' => '2900', 'name' => 'Other Liabilities',     'type' => 'liability', 'subtype' => 'other'],

        // EQUITY
        ['number' => '3000', 'name' => $equity_label,          'type' => 'equity',    'subtype' => 'equity'],
        ['number' => '3100', 'name' => $retained_label,        'type' => 'equity',    'subtype' => 'retained'],
        ['number' => '3200', 'name' => 'Owner Draw / Distributions', 'type' => 'equity', 'subtype' => 'draw'],

        // INCOME
        ['number' => '4000', 'name' => 'Service Revenue',       'type' => 'income',   'subtype' => 'service'],
        ['number' => '4100', 'name' => 'Product Sales',         'type' => 'income',   'subtype' => 'product'],
        ['number' => '4200', 'name' => 'Membership / Dues',     'type' => 'income',   'subtype' => 'membership'],
        ['number' => '4300', 'name' => 'Donations',             'type' => 'income',   'subtype' => 'donation'],
        ['number' => '4400', 'name' => 'Grants',                'type' => 'income',   'subtype' => 'grant'],
        ['number' => '4500', 'name' => 'Event Revenue',         'type' => 'income',   'subtype' => 'event'],
        ['number' => '4900', 'name' => 'Other Income',          'type' => 'income',   'subtype' => 'other'],

        // EXPENSES
        ['number' => '5000', 'name' => 'Rent',                  'type' => 'expense',  'subtype' => 'facilities'],
        ['number' => '5010', 'name' => 'Utilities',             'type' => 'expense',  'subtype' => 'facilities'],
        ['number' => '5020', 'name' => 'Internet & Phone',      'type' => 'expense',  'subtype' => 'facilities'],
        ['number' => '5100', 'name' => 'Wages & Salaries',      'type' => 'expense',  'subtype' => 'payroll'],
        ['number' => '5110', 'name' => 'Contractor Payments',   'type' => 'expense',  'subtype' => 'payroll'],
        ['number' => '5120', 'name' => 'Payroll Taxes',         'type' => 'expense',  'subtype' => 'payroll'],
        ['number' => '5200', 'name' => 'Supplies',              'type' => 'expense',  'subtype' => 'supplies'],
        ['number' => '5210', 'name' => 'Equipment & Tools',     'type' => 'expense',  'subtype' => 'supplies'],
        ['number' => '5300', 'name' => 'Marketing & Advertising', 'type' => 'expense', 'subtype' => 'marketing'],
        ['number' => '5310', 'name' => 'Website & Software',   'type' => 'expense',  'subtype' => 'marketing'],
        ['number' => '5400', 'name' => 'Professional Services', 'type' => 'expense',  'subtype' => 'professional'],
        ['number' => '5410', 'name' => 'Accounting & Legal',    'type' => 'expense',  'subtype' => 'professional'],
        ['number' => '5500', 'name' => 'Insurance',             'type' => 'expense',  'subtype' => 'insurance'],
        ['number' => '5600', 'name' => 'Bank Fees',             'type' => 'expense',  'subtype' => 'banking'],
        ['number' => '5610', 'name' => 'Credit Card Fees',      'type' => 'expense',  'subtype' => 'banking'],
        ['number' => '5700', 'name' => 'Travel & Transportation', 'type' => 'expense', 'subtype' => 'travel'],
        ['number' => '5710', 'name' => 'Meals & Entertainment', 'type' => 'expense',  'subtype' => 'travel'],
        ['number' => '5800', 'name' => 'Education & Training',  'type' => 'expense',  'subtype' => 'education'],
        ['number' => '5900', 'name' => 'Depreciation',          'type' => 'expense',  'subtype' => 'depreciation'],
        ['number' => '5950', 'name' => 'Miscellaneous',         'type' => 'expense',  'subtype' => 'misc'],
    ];
}

function seed_chart_of_accounts(int $company_id, string $org_type = 'for_profit'): void {
    $accounts = default_chart_of_accounts($org_type);
    $stmt = db()->prepare("
        INSERT INTO tb_accounts (company_id, account_number, name, type, subtype)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($accounts as $a) {
        $stmt->execute([$company_id, $a['number'], $a['name'], $a['type'], $a['subtype']]);
    }
}
