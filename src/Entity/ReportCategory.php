<?php

declare(strict_types=1);

namespace App\Entity;

enum ReportCategory: string
{
    case Route = 'route';
    case Cargo = 'cargo';
    case Quantities = 'quantities';
    case Other = 'other';

    public function label(): string
    {
        return 'report.category.'.$this->value;
    }
}
