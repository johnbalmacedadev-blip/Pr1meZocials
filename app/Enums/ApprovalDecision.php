<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::RevisionRequested => 'Revision requested',
            self::Rejected => 'Rejected',
        };
    }
}
