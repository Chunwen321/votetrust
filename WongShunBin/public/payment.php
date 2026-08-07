<?php
declare(strict_types=1);

if (!isset($_SESSION['user_id'])) { go('/'); }
$paymentId = filter_input(INPUT_GET, 'payment_id', FILTER_VALIDATE_INT);
$payment = $pdo->prepare('SELECT p.id, p.amount, p.status, e.title FROM payment p JOIN candidacy c ON c.payment_id = p.id JOIN election e ON e.id = c.election_id WHERE p.id = ? AND c.candidate_id = ?');
$payment->execute([$paymentId ?: 0, (int) $_SESSION['user_id']]);
$payment = $payment->fetch(PDO::FETCH_ASSOC);
if (!is_array($payment)) { http_response_code(404); echo 'Payment was not found.'; exit; }
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Make Payment | VoteTrust</title><link rel="stylesheet" href="/assets/app.css"></head><body><header><strong>VoteTrust</strong><span>Candidate Registration</span><a class="account" href="/profile">My Profile</a></header><main><h1>Make Payment</h1><section class="card"><h2><?= e((string) $payment['title']) ?></h2><p><strong>Payment ID:</strong> <?= (int) $payment['id'] ?></p><p><strong>Security deposit:</strong> MYR <?= e(number_format((float) $payment['amount'], 2)) ?></p><p><strong>Current status:</strong> <?= e((string) $payment['status']) ?></p><?php if ($payment['status'] === 'Pending'): ?><p>Continue with the Payment Module to complete this payment. Once its API confirms the payment, your candidacy application changes to Approved.</p><button class="primary" type="button" disabled>Payment Module checkout URL required</button><?php else: ?><p>Your payment has been completed.</p><?php endif ?></section><p><a href="/profile">Back to My Profile</a></p></main></body></html>
