<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "LINTING FILES:\n";
try {
    include_once __DIR__ . '/hub/platforms/InstagramHandler.php';
    echo "InstagramHandler: OK\n";
} catch (Throwable $t) {
    echo "InstagramHandler Error: " . $t->getMessage() . "\n";
}

try {
    include_once __DIR__ . '/hub/platforms/LinkedInHandler.php';
    echo "LinkedInHandler: OK\n";
} catch (Throwable $t) {
    echo "LinkedInHandler Error: " . $t->getMessage() . "\n";
}
echo "LINT END\n";
