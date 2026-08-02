<?php
$pageTitle = 'Edit election';
$defaults = [];
$formAction = url('/elections/' . $election->id());
$cancelUrl = url('/elections/' . $election->id());
$submitLabel = 'Save configuration';
?>
<section class="page-heading">
    <p class="eyebrow">Draft #<?= e($election->id()) ?></p>
    <h1>Edit election configuration</h1>
    <p>Configuration becomes locked after submission for payment.</p>
</section>
<?php require __DIR__ . '/_form.php'; ?>

