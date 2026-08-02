<?php

declare(strict_types=1);

use VoteTrust\Election\Core\FrozenClock;
use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\ElectionService;
use VoteTrust\Election\Tests\Fakes\FakePaymentGateway;
use VoteTrust\Election\Tests\Security\InsecureElectionWorkflow;

require __DIR__ . '/bootstrap.php';

$now = new DateTimeImmutable('2026-08-01 12:00:00');

echo "VoteTrust secure coding before/after demonstration\n";
echo str_repeat('=', 82) . "\n";

resetTestDatabase();
$organizer = makeTestUser();
$legacyElection = makeTestElection($organizer, $now);
(new InsecureElectionWorkflow())->forceStatus($legacyElection, Election::NOMINATION_OPEN);
printf("BEFORE  %-43s %s\n", 'No password supplied', $legacyElection->status());
printf("BEFORE  %-43s %s\n", 'Organiser bypasses admin approval', 'NOMINATION_OPEN (VULNERABLE)');

resetTestDatabase();
$organizer = makeTestUser();
$secureElection = makeTestElection($organizer, $now);
$paymentBlockedService = new ElectionService(
    paymentGateway: new FakePaymentGateway(false),
    clock: new FrozenClock($now)
);

try {
    $paymentBlockedService->transition($organizer, $secureElection->id(), 'submit_for_payment', '');
    $reauthResult = 'UNEXPECTEDLY ALLOWED';
} catch (AuthorizationException) {
    $reauthResult = 'BLOCKED (PASSWORD REQUIRED)';
}
printf("AFTER   %-43s %s\n", 'No password supplied', $reauthResult);

$paymentBlockedService->transition($organizer, $secureElection->id(), 'submit_for_payment', 'Correct!Pass1');
try {
    $paymentBlockedService->transition(
        $organizer,
        $secureElection->id(),
        'verify_payment',
        'Correct!Pass1',
        ['payment_reference' => 'VT-HOST-PENDING-001']
    );
    $paymentResult = 'UNEXPECTEDLY ADVANCED';
} catch (BusinessRuleException) {
    $paymentResult = Election::find($secureElection->id())?->status() . ' (PAYMENT GATE HELD)';
}
printf("AFTER   %-43s %s\n", 'Unconfirmed payment supplied', $paymentResult);

resetTestDatabase();
$organizer = makeTestUser();
$admin = makeTestAdmin();
$secureElection = makeTestElection($organizer, $now);
$paidService = new ElectionService(paymentGateway: new FakePaymentGateway(true), clock: new FrozenClock($now));
$paidService->transition($organizer, $secureElection->id(), 'submit_for_payment', 'Correct!Pass1');
$paidService->transition($organizer, $secureElection->id(), 'verify_payment', 'Correct!Pass1', [
    'payment_reference' => 'VT-HOST-PAID-001',
]);
printf("AFTER   %-43s %s\n", 'Confirmed payment supplied', Election::find($secureElection->id())?->status() . ' (ADMIN GATE HELD)');
try {
    $paidService->transition($organizer, $secureElection->id(), 'approve', 'Correct!Pass1');
    $approvalResult = 'UNEXPECTEDLY APPROVED';
} catch (AuthorizationException) {
    $approvalResult = Election::find($secureElection->id())?->status() . ' (ORGANISER FORBIDDEN)';
}
printf("AFTER   %-43s %s\n", 'Organiser attempts self-approval', $approvalResult);
$approved = $paidService->transition($admin, $secureElection->id(), 'approve', 'Admin!Pass1');
printf("AFTER   %-43s %s\n", 'Independent administrator approves', $approved->status());

echo str_repeat('-', 82) . "\n";
echo "The vulnerable workflow exists only as a CLI test fixture; it is not web-routable.\n";
