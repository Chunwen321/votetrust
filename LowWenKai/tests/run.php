<?php

declare(strict_types=1);

use VoteTrust\Election\Core\FrozenClock;
use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Security\ApiRequestValidator;
use VoteTrust\Election\Services\ElectionService;
use VoteTrust\Election\Tests\Fakes\FakePaymentGateway;
use VoteTrust\Election\Tests\TestRunner;

require __DIR__ . '/bootstrap.php';

$runner = new TestRunner();

$runner->add('State pattern follows payment, independent admin approval, voting and closure', static function (): void {
    resetTestDatabase();
    $now = new DateTimeImmutable('2026-08-01 12:00:00');
    $clock = new FrozenClock($now);
    $organizer = makeTestUser();
    $admin = makeTestAdmin();
    $election = makeTestElection($organizer, $now);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(true), clock: $clock);

    $election = $service->transition($organizer, $election->id(), 'submit_for_payment', 'Correct!Pass1');
    TestRunner::assertSame(Election::PENDING_PAYMENT, $election->status());

    $election = $service->transition($organizer, $election->id(), 'verify_payment', 'Correct!Pass1', [
        'payment_reference' => 'VT-HOST-PAID-001',
    ]);
    TestRunner::assertSame(Election::PENDING_ADMIN_APPROVAL, $election->status());
    TestRunner::assertTrue($election->paymentVerified());
    TestRunner::assertTrue(!$election->adminApproved());

    $election = $service->transition($admin, $election->id(), 'approve', 'Admin!Pass1');
    TestRunner::assertSame(Election::NOMINATION_OPEN, $election->status());
    TestRunner::assertTrue($election->adminApproved());

    $election = $service->transition($organizer, $election->id(), 'start_voting', 'Correct!Pass1');
    TestRunner::assertSame(Election::VOTING_OPEN, $election->status());
    TestRunner::assertThrows(BusinessRuleException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'close_voting',
        'Correct!Pass1'
    ));

    $clock->set($now->modify('+2 hours'));
    $election = $service->transition($organizer, $election->id(), 'close_voting', 'Correct!Pass1');
    TestRunner::assertSame(Election::CLOSED, $election->status());
    TestRunner::assertSame(5, count($election->transitionLogs()));
});

$runner->add('State pattern blocks lifecycle skipping', static function (): void {
    resetTestDatabase();
    $now = new DateTimeImmutable('2026-08-01 12:00:00');
    $organizer = makeTestUser();
    $election = makeTestElection($organizer, $now);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(true), clock: new FrozenClock($now));

    TestRunner::assertThrows(BusinessRuleException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'start_voting',
        'Correct!Pass1'
    ));
    TestRunner::assertSame(Election::DRAFT, Election::find($election->id())?->status());
});

$runner->add('Critical operations reject missing or wrong reauthentication', static function (): void {
    resetTestDatabase();
    $now = new DateTimeImmutable('2026-08-01 12:00:00');
    $organizer = makeTestUser();
    $election = makeTestElection($organizer, $now);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(true), clock: new FrozenClock($now));

    TestRunner::assertThrows(AuthorizationException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'submit_for_payment',
        ''
    ));
    TestRunner::assertThrows(AuthorizationException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'submit_for_payment',
        'WrongPassword'
    ));
    TestRunner::assertSame(Election::DRAFT, Election::find($election->id())?->status());
});

$runner->add('Ownership access control rejects a different organiser', static function (): void {
    resetTestDatabase();
    $owner = makeTestUser();
    $other = makeTestUser('OtherOrganizer', 'Other!Pass1');
    $election = makeTestElection($owner);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(true));

    TestRunner::assertThrows(AuthorizationException::class, static fn () => $service->findOwned($other, $election->id()));
});

$runner->add('Payment verification gate keeps the election pending', static function (): void {
    resetTestDatabase();
    $now = new DateTimeImmutable('2026-08-01 12:00:00');
    $organizer = makeTestUser();
    $election = makeTestElection($organizer, $now);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(false), clock: new FrozenClock($now));
    $service->transition($organizer, $election->id(), 'submit_for_payment', 'Correct!Pass1');

    TestRunner::assertThrows(BusinessRuleException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'verify_payment',
        'Correct!Pass1',
        ['payment_reference' => 'VT-HOST-PENDING-001']
    ));
    TestRunner::assertSame(Election::PENDING_PAYMENT, Election::find($election->id())?->status());
});

$runner->add('Separation of duties blocks organiser self-approval and requires an admin', static function (): void {
    resetTestDatabase();
    $now = new DateTimeImmutable('2026-08-01 12:00:00');
    $organizer = makeTestUser();
    $admin = makeTestAdmin();
    $election = makeTestElection($organizer, $now);
    $service = new ElectionService(paymentGateway: new FakePaymentGateway(true), clock: new FrozenClock($now));
    $service->transition($organizer, $election->id(), 'submit_for_payment', 'Correct!Pass1');
    $service->transition($organizer, $election->id(), 'verify_payment', 'Correct!Pass1', [
        'payment_reference' => 'VT-HOST-PAID-001',
    ]);

    TestRunner::assertThrows(AuthorizationException::class, static fn () => $service->transition(
        $organizer,
        $election->id(),
        'approve',
        'Correct!Pass1'
    ));
    TestRunner::assertThrows(AuthorizationException::class, static fn () => $service->transition(
        $admin,
        $election->id(),
        'approve',
        'WrongAdminPassword'
    ));
    TestRunner::assertSame(Election::PENDING_ADMIN_APPROVAL, Election::find($election->id())?->status());
});

$runner->add('Optimistic locking rejects stale simultaneous updates', static function (): void {
    resetTestDatabase();
    $organizer = makeTestUser();
    $election = makeTestElection($organizer);
    $firstCopy = Election::find($election->id());
    $secondCopy = Election::find($election->id());
    TestRunner::assertTrue($firstCopy instanceof Election && $secondCopy instanceof Election);

    $firstCopy->set('title', 'First concurrent edit wins');
    $firstCopy->save();
    $secondCopy->set('title', 'Stale edit must fail');
    TestRunner::assertThrows(\VoteTrust\Election\Exceptions\ConcurrencyException::class, static fn () => $secondCopy->save());
});

$runner->add('IFA request validator requires a traceable fresh envelope', static function (): void {
    $validator = new ApiRequestValidator();
    $valid = $validator->validateEnvelope([
        'requestID' => 'CANDIDATE-REQ-001',
        'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    ]);
    TestRunner::assertSame('CANDIDATE-REQ-001', $valid['requestID']);
    TestRunner::assertThrows(\VoteTrust\Election\Exceptions\ValidationException::class, static fn () => $validator->validateEnvelope([
        'requestID' => 'short',
        'timestamp' => '2020-01-01T00:00:00+00:00',
    ]));
});

exit($runner->run());
