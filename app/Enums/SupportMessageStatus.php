<?php

namespace App\Enums;

enum SupportMessageStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Consumed = 'consumed';
}
