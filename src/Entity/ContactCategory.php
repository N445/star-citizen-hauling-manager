<?php

declare(strict_types=1);

namespace App\Entity;

enum ContactCategory: string
{
    case Improvement = 'improvement';
    case Bug = 'bug';
    case Question = 'question';
    case Other = 'other';

    public function label(): string
    {
        return 'contact.category.'.$this->value;
    }
}
