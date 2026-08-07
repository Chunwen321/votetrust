<?php
/** @var string $content */
/** @var Closure(string):string $url */
$safe = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$signedInName = is_array($voter ?? null) ? (string) ($voter['name'] ?? '') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= $safe($pageTitle ?? 'Voting Process') ?> &middot; VoteTrust</title>
    <link rel="stylesheet" href="<?= $safe($url('/assets/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $safe($url('/voting')) ?>" aria-label="VoteTrust voting home">
        <span class="brand-mark" aria-hidden="true">VT</span>
        <span><strong>VoteTrust</strong><small>Voting Process</small></span>
    </a>
    <?php if ($signedInName !== ''): ?>
        <nav class="nav-actions" aria-label="Voter navigation">
            <a class="text-link" href="<?= $safe($url('/voting')) ?>">Voting portal</a>
            <span class="user-chip"><?= $safe($signedInName) ?> &middot; Voter</span>
        </nav>
    <?php endif; ?>
</header>

<main class="page-shell"><?= $content ?></main>

<footer class="site-footer">
    <span>SDG 16 &middot; Peace, Justice and Strong Institutions</span>
    <span>Goh Chun Wen &middot; Voting Process Module</span>
</footer>
</body>
</html>
