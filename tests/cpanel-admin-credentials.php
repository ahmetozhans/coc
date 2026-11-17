<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cpanel/private/reviews.php';

function ensure(bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException('Admin credential test failed');
    }
}

ensure(!valid_admin_setup_code('short'));
ensure(!valid_admin_setup_code(str_repeat('!', 32)));
ensure(valid_admin_setup_code(bin2hex(random_bytes(24))));

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE admin_credentials (id INTEGER PRIMARY KEY, password_hash TEXT)');
$db->exec('INSERT INTO admin_credentials (id, password_hash) VALUES (1, NULL)');
ensure(initialize_admin_password($db, 'local-test-password'));
ensure(!initialize_admin_password($db, 'different-local-password'));
$hash = $db->query('SELECT password_hash FROM admin_credentials WHERE id = 1')->fetchColumn();
ensure(is_string($hash) && $hash !== 'local-test-password' && password_verify('local-test-password', $hash));
ensure(!password_verify('different-local-password', $hash));
ensure(admin_username() === 'admin' && admin_username('Mahan.Pro') === 'mahan.pro');
ensure(admin_login_matches('ADMIN', 'local-test-password', 'admin', $hash));
ensure(!admin_login_matches('guest', 'local-test-password', 'admin', $hash));
ensure(!admin_login_matches('admin', 'different-local-password', 'admin', $hash));
ensure(!admin_login_matches(['admin'], 'local-test-password', 'admin', $hash));
try {
    admin_username('<script>');
    ensure(false);
} catch (RuntimeException $error) {
    ensure($error->getMessage() === 'Invalid configured administrator username');
}
echo "Admin password hashing and one-time initialization passed.\n";
