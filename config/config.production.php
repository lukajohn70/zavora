<?php
/**
 * ZAVORA Marketplace — Production Configuration Preset
 * John Luka Media (JLM) Digital Ecosystem
 *
 * This file can be required or used as a reference for hardcoded zero-overhead
 * production deployments on cPanel or Docker/Kubernetes clusters.
 */

return [
    'app' => [
        'name'        => 'ZAVORA',
        'env'         => 'production',
        'debug'       => false,
        'url'         => 'https://zavora.com.ng',
        'currency'    => 'NGN',
        'currency_symbol' => '₦',
        'commission_rate' => 0.15, // 15% marketplace fee
    ],
    'database' => [
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'port'      => 3306,
        'database'  => 'gwylvxeo_zavora',
        'username'  => 'gwylvxeo_zavora',
        'password'  => 'CHANGE_ME_PRODUCTION_DB_PASS',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'paystack' => [
        'public_key' => getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_live_YOUR_PAYSTACK_PUBLIC_KEY',
        'secret_key' => getenv('PAYSTACK_SECRET_KEY') ?: 'sk_live_YOUR_PAYSTACK_SECRET_KEY',
    ],
    'ecosystem' => [
        'shared_secret'    => 'eco_sec_jlm_zavora_01a2c11beee84a1bb6e5ce40a29769f4',
        'corporate_url'    => 'https://jlm.com.ng',
        'learnerium_url'   => 'https://learnerium.jlm.com.ng',
        'learnerium_api'   => 'https://learnerium.jlm.com.ng/api/v1',
        'lms_results_url'  => 'https://lms.jlm.com.ng',
    ],
    'security' => [
        'session_lifetime' => 86400 * 7, // 7 days
        'sso_token_ttl'    => 900,       // 15 minutes
    ],
];
