<?php

namespace Bizorca\Consulting\Controllers;

use Bizorca\Consulting\Auth\CSRF;
use Bizorca\Consulting\Auth\Session;
use Bizorca\Consulting\Models\Application;

class ApplicationController
{
    public function show(): void
    {
        $user   = Session::user();
        $errors = Session::getFlash('errors') ?? [];
        $old    = Session::getFlash('old')    ?? [];
        render('apply', compact('user', 'errors', 'old'));
    }

    public function submit(): void
    {
        CSRF::verify();

        $fields = [
            'first_name', 'last_name', 'email', 'company_name', 'company_type',
            'website', 'revenue_range', 'employee_count', 'proc_location',
            'two_weeks', 'core_problem', 'desired_outcome', 'timeline',
            'budget_range', 'referral_source',
        ];

        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }

        // Required fields
        $required = ['first_name', 'last_name', 'email', 'company_name',
                     'revenue_range', 'employee_count', 'proc_location', 'two_weeks',
                     'core_problem', 'desired_outcome', 'timeline', 'budget_range'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = 'This field is required.';
            }
        }

        foreach (Application::OPTIONS as $field => $allowed) {
            if (!isset($errors[$field]) && !in_array($data[$field], $allowed, true)) {
                $errors[$field] = 'Please choose one of the options.';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            redirect('/apply');
        }

        // Attach user_id if logged in
        $user = Session::user();
        $data['user_id'] = $user ? $user['id'] : null;
        $data['notes']   = trim($_POST['notes'] ?? '');

        Application::create($data);

        redirect('/apply/thank-you');
    }

    public function thankYou(): void
    {
        render('apply-thanks');
    }
}
