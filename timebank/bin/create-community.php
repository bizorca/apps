<?php
/**
 * Create a community. The original had no way to do this except SQL (its
 * landing page's "Start a TimeBank" is a mailto); this does what its
 * database.sql seed did for "demo": the tenant, the standard categories and
 * email templates, and optionally a first admin.
 *
 *   php timebank/bin/create-community.php <slug> "<Name>" [admin-email] [--approval] [--welcome=1.00]
 *
 * The admin email must already be a tools account (they joined
 * tools.bizorca.com); they become this community's super_admin.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('TM_ROOT', dirname(__DIR__));
require TM_ROOT . '/includes/config.php';

use TimeBank\Core\Tenant;
use TimeBank\Models\EmailTemplate;

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => !str_starts_with($a, '--')));
$opts = array_values(array_filter(array_slice($argv, 1), fn($a) => str_starts_with($a, '--')));
[$slug, $name, $adminEmail] = $args + [null, null, null];

if (!$slug || !$name || !preg_match('/^[a-z0-9][a-z0-9-]{1,48}$/', $slug) || in_array($slug, Tenant::RESERVED, true)) {
    fwrite(STDERR, "Usage: php create-community.php <slug a-z0-9-> \"<Name>\" [admin-email] [--approval] [--welcome=1.00]\n");
    exit(1);
}

$welcome  = '1.00';
$approval = false;
foreach ($opts as $o) {
    if ($o === '--approval') {
        $approval = true;
    } elseif (preg_match('/^--welcome=(\d+(\.\d{1,2})?)$/', $o, $m)) {
        $welcome = number_format((float) $m[1], 2, '.', '');
    }
}

$db = tl_db();
$db->beginTransaction();
try {
    $exists = $db->prepare('SELECT id FROM tm_tenants WHERE subdomain = ?');
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException("a community '{$slug}' already exists");
    }

    $db->prepare(
        'INSERT INTO tm_tenants (subdomain, name, timezone, currency_name, currency_name_plural, welcome_credits, is_active, settings)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
    )->execute([$slug, $name, 'America/Los_Angeles', 'Hour', 'Hours', $welcome, json_encode([
        'allow_self_registration' => true,
        'require_approval'        => $approval,
        'max_balance'             => 20.00,
        'min_balance'             => -5.00,
        'allow_negative_balance'  => true,
    ])]);
    $tenantId = (int) $db->lastInsertId();

    $categories = ['Health & Wellness' => 'heart', 'Education & Tutoring' => 'book-open', 'Home & Garden' => 'home',
        'Food & Cooking' => 'utensils', 'Arts & Crafts' => 'palette', 'Transportation' => 'car', 'Pet Care' => 'paw-print',
        'Technology Help' => 'monitor', 'Child Care' => 'baby', 'Elderly Care' => 'user-check',
        'Legal & Financial' => 'briefcase', 'Spiritual & Emotional Support' => 'sun', 'Other' => 'tag'];
    $cat = $db->prepare('INSERT INTO tm_categories (tenant_id, name, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
    $i = 0;
    foreach ($categories as $catName => $icon) {
        $cat->execute([$tenantId, $catName, $icon, ++$i]);
    }

    $tpl = $db->prepare('INSERT INTO tm_email_templates (tenant_id, slug, name, subject, body, variables) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (EmailTemplate::getDefaultTemplates() as $tplSlug => $t) {
        $tpl->execute([$tenantId, $tplSlug, $t['name'], $t['subject'], $t['body'], $t['variables']]);
    }

    if ($adminEmail) {
        $u = $db->prepare('SELECT * FROM users WHERE email = ?');
        $u->execute([strtolower(trim($adminEmail))]);
        $user = $u->fetch();
        if (!$user) {
            throw new RuntimeException("{$adminEmail} has no tools account yet; they should create one at /account/register.php first");
        }
        $parts = explode(' ', trim((string) $user['name']), 2);
        $db->prepare(
            "INSERT INTO tm_members (tenant_id, user_id, email, role, first_name, last_name, display_name, is_active, is_approved, balance)
             VALUES (?, ?, ?, 'super_admin', ?, ?, ?, 1, 1, 0.00)"
        )->execute([$tenantId, (int) $user['id'], $user['email'], $parts[0], $parts[1] ?? '', $user['name']]);
    }

    $db->commit();
    echo "Created community '{$slug}' ({$name}), id {$tenantId}: " . count($categories) . " categories, "
        . count(EmailTemplate::getDefaultTemplates()) . " email templates" . ($adminEmail ? ", admin {$adminEmail}" : '') . ".\n";
    echo "  " . TM_ORIGIN . community_url($slug) . "\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Not created: ' . $e->getMessage() . "\n");
    exit(1);
}
