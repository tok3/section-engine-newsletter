<?php

return [
    'route_prefix' => 'newsletter',
    'default_locale' => null,
    'mailer' => null,
    'queue' => null,
    'from' => ['address' => null, 'name' => null],
    'mail' => [
        'brand_name' => config('app.name'),
        'logo_url' => null,
        'footer' => null,
    ],
    'confirmation_ttl_hours' => 48,
    'context_ttl_hours' => 168,
    'resend_cooldown_minutes' => 10,
    'min_fill_seconds' => 3,
    'require_checkbox' => true,
    'styles' => true,
    'validate_dns' => false,
    'rate_limits' => ['per_ip' => 10, 'per_email' => 3],
    'prune' => ['pending_days' => 30, 'unsubscribed_proof_years' => 3],
    'views' => ['variants' => ['default' => 'section-engine-newsletter::form.default', 'compact' => 'section-engine-newsletter::form.compact']],
];
