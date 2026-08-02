<?php
$pageTitle = 'Create election';
$election = null;
$formAction = url('/elections');
$cancelUrl = url('/elections');
$submitLabel = 'Create draft';
?>
<section class="page-heading">
    <p class="eyebrow">New election</p>
    <h1>Build a trustworthy election</h1>
    <p>Start with a private draft. Publishing remains blocked until hosting payment is independently confirmed.</p>
</section>
<?php require __DIR__ . '/_form.php'; ?>

