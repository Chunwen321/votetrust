<?php

namespace App\Services\Voting;

use RuntimeException;

// PIC: Goh Chun Wen - Safe validation failure for the Voting Process service boundary.
final class InvalidVoteRequest extends RuntimeException {}
