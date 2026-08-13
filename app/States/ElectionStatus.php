<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

enum ElectionStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PENDING_ADMIN_APPROVAL = 'PENDING_ADMIN_APPROVAL';
    case NOMINATION_OPEN = 'NOMINATION_OPEN';
    case VOTING_OPEN = 'VOTING_OPEN';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';
}
