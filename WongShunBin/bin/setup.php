<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config.php';
$sql = file_get_contents(dirname(__DIR__) . '/election_db.sql');
if (!is_string($sql)) throw new RuntimeException('Cannot find election_db.sql.');
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) if (trim($statement) !== '') candidatePdo()->exec($statement);
echo "VoteTrust candidate registration database is ready.\n";
