<?php

return [
    'base_currency' => env('FINANCIAL_BASE_CURRENCY', 'NIO'),
    'currency_symbol' => env('FINANCIAL_CURRENCY_SYMBOL', 'C$'),

    // Critical rules intentionally remain unset until the business approves them.
    'interest_method' => env('FINANCIAL_INTEREST_METHOD'),
    'payment_allocation_order' => array_values(array_filter(explode(',', (string) env('FINANCIAL_PAYMENT_ORDER', '')))),
    'early_payment_strategy' => env('FINANCIAL_EARLY_PAYMENT_STRATEGY'),
    'delinquency_method' => env('FINANCIAL_DELINQUENCY_METHOD'),
];
