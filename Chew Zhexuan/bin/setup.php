<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Config;
use VoteTrust\UserAdmin\Core\Database;

$root = dirname(__DIR__);
require $root . '/autoload.php';
Config::load($root);

$sql = file_get_contents($root . '/database/schema.sql');
if ($sql === false) {
    throw new RuntimeException('Unable to read the User Module schema file.');
}

$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
$pdo = Database::connection();
foreach ($statements as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}

echo "User, Voter, and Admin Module schema applied.\n";
