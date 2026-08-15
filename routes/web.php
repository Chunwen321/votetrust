<?php

use Illuminate\Support\Facades\Route;

// PIC: Chew Zhexuan - User/auth routes.
require __DIR__.'/user.php';

// PIC: Low Wen Kai - Election Management routes.
require __DIR__.'/election.php';

// PIC: Wong Shun Bin - Candidate Registration routes.
require __DIR__.'/candidate.php';

// PIC: Goh Chun Wen - Voting Process routes.
require __DIR__.'/voting.php';

// PIC: Risky Puah Kaho - Payment Module - Payment settlement routes.
require __DIR__.'/payment.php';

// PIC: Goh Chun Wen, Chew Zhexuan, Wong Shun Bin, Low Wen Kai - shared entry page.
Route::view('/', 'home')->name('home');
