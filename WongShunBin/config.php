<?php
declare(strict_types=1);
function candidatePdo(): PDO {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    require dirname(__DIR__) . '/db.php';
    $connection = $pdo;
    return $connection;
}
function candidateUploadDirectory(): string { return __DIR__ . '/storage/candidate_uploads'; }
function candidatePhotoDirectory(): string { return candidateUploadDirectory() . '/photos'; }
function candidateDocumentDirectory(): string { return candidateUploadDirectory() . '/documents'; }
