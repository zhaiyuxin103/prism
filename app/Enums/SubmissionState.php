<?php

namespace App\Enums;

enum SubmissionState: string
{
    case Pending = 'pending';
    case Uncertain = 'uncertain';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
