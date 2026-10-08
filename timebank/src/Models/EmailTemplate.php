<?php

declare(strict_types=1);

namespace TimeBank\Models;

use TimeBank\Core\DB;

class EmailTemplate extends BaseModel
{
    protected string $table = 'tm_email_templates';

    /**
     * Fetch a single template by its slug for a tenant.
     */
    public function getBySlug(int $tenantId, string $slug): array|false
    {
        return DB::fetch(
            "SELECT * FROM `tm_email_templates` WHERE tenant_id = ? AND slug = ? LIMIT 1",
            [$tenantId, $slug]
        );
    }

    /**
     * All templates for a tenant, ordered alphabetically by name.
     */
    public function getAllForTenant(int $tenantId): array
    {
        return DB::fetchAll(
            "SELECT * FROM `tm_email_templates` WHERE tenant_id = ? ORDER BY name ASC",
            [$tenantId]
        );
    }

    /**
     * Replace {{variable}} placeholders in both the subject and body
     * of a template row. Returns a [subject, body] tuple.
     *
     * @param array  $template  A row from email_templates (requires 'subject' and 'body')
     * @param array  $variables Key-value pairs, e.g. ['first_name' => 'Alice']
     * @return array{0: string, 1: string} [rendered subject, rendered body]
     */
    public function render(array $template, array $variables): array
    {
        $search  = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[]  = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }

        $subject = str_replace($search, $replace, $template['subject']);
        $body    = str_replace($search, $replace, $template['body']);

        return [$subject, $body];
    }

    /**
     * Default template definitions. Used when seeding a new tenant or
     * resetting templates to their out-of-the-box content.
     *
     * @return array<string, array{name: string, subject: string, body: string, variables: string}>
     */
    public static function getDefaultTemplates(): array
    {
        return [
            'welcome' => [
                'name'      => 'Welcome Email',
                'subject'   => 'Welcome to {{community_name}}, {{first_name}}!',
                'body'      => "Hi {{first_name}},\n\nWelcome to {{community_name}}! We're so glad you're here.\n\nYour account has been created and you've been credited with {{welcome_credits}} {{currency_name}} to get started. Browse what your neighbors are offering, post something you can help with, and start making connections.\n\nTo log in, visit: {{login_url}}\n\nIf you have any questions, just reply to this email or reach out to an admin.\n\nWarmly,\nThe {{community_name}} Team",
                'variables' => 'first_name,community_name,welcome_credits,currency_name,login_url',
            ],

            'transaction_recorded' => [
                'name'      => 'Transaction Recorded',
                'subject'   => 'A transaction has been recorded — {{hours}} {{currency_unit}} with {{other_member}}',
                'body'      => "Hi {{first_name}},\n\nA transaction has been recorded on your account.\n\n  Service: {{description}}\n  Date: {{service_date}}\n  Hours: {{hours}}\n  With: {{other_member}}\n  Your new balance: {{balance}} {{currency_name_plural}}\n\nIf you believe this transaction was recorded in error, please contact your timebank admin.\n\nThank you for being part of {{community_name}}!",
                'variables' => 'first_name,hours,currency_unit,other_member,description,service_date,balance,currency_name_plural,community_name',
            ],

            'new_message' => [
                'name'      => 'New Message Notification',
                'subject'   => 'New message from {{sender_name}}: {{subject}}',
                'body'      => "Hi {{first_name}},\n\nYou have a new message from {{sender_name}}.\n\nSubject: {{subject}}\n\n\"{{preview}}\"\n\nLog in to read and reply: {{message_url}}\n\n— {{community_name}}",
                'variables' => 'first_name,sender_name,subject,preview,message_url,community_name',
            ],

            'weekly_digest' => [
                'name'      => 'Weekly Digest',
                'subject'   => 'Your {{community_name}} weekly update — {{week_ending}}',
                'body'      => "Hi {{first_name}},\n\nHere's what's been happening in {{community_name}} this week:\n\nNew offers this week: {{new_offers_count}}\nTransactions recorded: {{transactions_count}}\nTotal hours exchanged: {{hours_exchanged}}\nYour current balance: {{balance}} {{currency_name_plural}}\n\nNew offers from your neighbors:\n{{recent_offers_list}}\n\nLog in to see everything: {{dashboard_url}}\n\nHave a great week,\n{{community_name}}",
                'variables' => 'first_name,community_name,week_ending,new_offers_count,transactions_count,hours_exchanged,balance,currency_name_plural,recent_offers_list,dashboard_url',
            ],

            'password_reset' => [
                'name'      => 'Password Reset',
                'subject'   => 'Reset your {{community_name}} password',
                'body'      => "Hi {{first_name}},\n\nSomeone requested a password reset for your account at {{community_name}}.\n\nClick the link below to reset your password. This link expires in {{expires_in}}.\n\n{{reset_url}}\n\nIf you did not request a password reset, you can safely ignore this email. Your password has not been changed.\n\n— {{community_name}}",
                'variables' => 'first_name,community_name,reset_url,expires_in',
            ],

            'donation_request' => [
                'name'      => 'Community Fund Donation Request',
                'subject'   => 'Support {{community_name}} — a small donation keeps us running',
                'body'      => "Hi {{first_name}},\n\n{{community_name}} runs on the generosity of its members. We're asking each member to contribute {{suggested_amount}} to help cover operating costs this year.\n\nYou can donate via:\n- PayPal: {{paypal_url}}\n- Credit card: {{stripe_url}}\n- Or donate {{hours_equivalent}} {{currency_name_plural}} from your time balance\n\nIf you're experiencing financial hardship, just let us know and we can work something out.\n\nTo make your contribution: {{donation_url}}\n\nThank you for supporting your community,\n{{community_name}}",
                'variables' => 'first_name,community_name,suggested_amount,paypal_url,stripe_url,hours_equivalent,currency_name_plural,donation_url',
            ],

            'approval_needed' => [
                'name'      => 'New Member Awaiting Approval',
                'subject'   => 'New member registration: {{member_name}} is waiting for approval',
                'body'      => "Hi {{admin_name}},\n\nA new member has registered and is waiting for approval to join {{community_name}}.\n\nName: {{member_name}}\nEmail: {{member_email}}\nRegistered: {{registered_at}}\n\nReview and approve this member here: {{approval_url}}\n\n— {{community_name}} Admin System",
                'variables' => 'admin_name,community_name,member_name,member_email,registered_at,approval_url',
            ],
        ];
    }
}
