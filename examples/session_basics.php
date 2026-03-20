<?php

declare(strict_types=1);

/**
 * Example: namespaced session containers and validators with laminas-session.
 *
 * Run from the laminas-session project root (requires a web context or CLI with session.save_path writable):
 *   php examples/session_basics.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Laminas\Session\Container;
use Laminas\Session\Config\StandardConfig;
use Laminas\Session\SessionManager;
use Laminas\Session\Validator\HttpUserAgent;
use Laminas\Session\Validator\RemoteAddr;

// --- Configure session manager ---
$config = new StandardConfig([
    'cookie_lifetime' => 3600,
    'gc_maxlifetime'  => 3600,
    'use_cookies'     => false, // off for CLI demo
    'use_only_cookies'=> false,
    'save_path'       => sys_get_temp_dir(),
    'name'            => 'myapp_session',
]);

$manager = new SessionManager($config);

// Attach validators to protect against session fixation/hijacking
$manager->get_validator_chain()
    ->attach(new HttpUserAgent())
    ->attach(new RemoteAddr());

Container::set_default_manager($manager);

// --- Namespaced container ---
$user = new Container('user');
$user->id    = 42;
$user->name  = 'Alice';
$user->roles = ['admin', 'editor'];

echo "User ID:   " . $user->id   . "\n";
echo "User name: " . $user->name . "\n";
echo "Roles:     " . implode(', ', $user->roles) . "\n\n";

// --- Expiry by hop count ---
$flash = new Container('flash');
$flash->set_expiration_hops(1, 'message');
$flash->message = 'Your profile was saved successfully.';

echo "Flash message: " . $flash->message . "\n";

// --- Check existence ---
echo "Has 'id':   " . ($user->offsetExists('id')   ? 'yes' : 'no') . "\n";
echo "Has 'email': " . ($user->offsetExists('email') ? 'yes' : 'no') . "\n";
