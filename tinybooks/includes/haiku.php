<?php
require_once __DIR__ . '/functions.php';

function haiku_request(string $prompt): ?string {
    // Shared server key (private_html/.env.php), the same one Proforma uses.
    // It used to be typed into TinyBooks' Settings page and stored in the database.
    $api_key = (string)tl_env('ANTHROPIC_API_KEY', '');
    if (!$api_key) return null;

    $payload = json_encode([
        'model'      => ANTHROPIC_MODEL,
        'max_tokens' => 256,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    $ch = curl_init(ANTHROPIC_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) return null;

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? null;
}

function ai_categorize_transaction(string $description, float $amount, array $accounts): ?array {
    $account_list = implode("\n", array_map(
        fn($a) => "{$a['id']}: {$a['name']} ({$a['type']})",
        $accounts
    ));

    $prompt = "You are a bookkeeping assistant. Given this transaction, suggest the most likely account category.

Transaction description: \"{$description}\"
Amount: \${$amount}

Available accounts:
{$account_list}

Respond with ONLY a JSON object in this exact format:
{\"account_id\": <number>, \"confidence\": \"high|medium|low\", \"reason\": \"<brief reason>\"}

Pick the single most appropriate account. If you cannot determine it, use the lowest-numbered expense account.";

    $response = haiku_request($prompt);
    if (!$response) return null;

    // Extract JSON from response
    preg_match('/\{[^}]+\}/', $response, $matches);
    if (!$matches) return null;

    $result = json_decode($matches[0], true);
    return is_array($result) ? $result : null;
}

function ai_check_duplicate(array $new_tx, array $recent_transactions): ?array {
    if (empty($recent_transactions)) return null;

    $tx_list = implode("\n", array_map(
        fn($t) => "ID {$t['id']}: {$t['date']} - {$t['description']} - \${$t['amount']}",
        array_slice($recent_transactions, 0, 20)
    ));

    $prompt = "You are a bookkeeping assistant checking for duplicate transactions.

New transaction:
Date: {$new_tx['date']}
Description: {$new_tx['description']}
Amount: \${$new_tx['amount']}

Recent transactions:
{$tx_list}

Is the new transaction likely a duplicate of any recent transaction? Consider the date (within 3 days), description similarity, and amount.

Respond with ONLY a JSON object:
{\"is_duplicate\": true|false, \"duplicate_id\": <id or null>, \"confidence\": \"high|medium|low\", \"reason\": \"<brief reason>\"}";

    $response = haiku_request($prompt);
    if (!$response) return null;

    preg_match('/\{[^}]+\}/', $response, $matches);
    if (!$matches) return null;

    $result = json_decode($matches[0], true);
    return is_array($result) ? $result : null;
}

function ai_reconciliation_help(array $unmatched_statement, array $unmatched_books): string {
    if (empty($unmatched_statement) && empty($unmatched_books)) {
        return "Everything matches up. You're done.";
    }

    $stmt_list = implode("\n", array_map(
        fn($t) => "{$t['date']}: {$t['description']} \${$t['amount']}",
        array_slice($unmatched_statement, 0, 10)
    ));
    $books_list = implode("\n", array_map(
        fn($t) => "{$t['date']}: {$t['description']} \${$t['amount']}",
        array_slice($unmatched_books, 0, 10)
    ));

    $prompt = "You are a bookkeeping assistant helping reconcile a bank account.

Transactions on the bank statement but NOT in the books:
" . ($stmt_list ?: '(none)') . "

Transactions in the books but NOT on the bank statement:
" . ($books_list ?: '(none)') . "

In 2-3 plain sentences (no jargon, explain like to a non-accountant), explain what might be going on and what action to take. Be direct and practical.";

    return haiku_request($prompt) ?? "AI help is unavailable right now.";
}
