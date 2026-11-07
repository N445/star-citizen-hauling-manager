<?php

declare(strict_types=1);

namespace App\Entity;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return 'report.status.'.$this->value;
    }
}
