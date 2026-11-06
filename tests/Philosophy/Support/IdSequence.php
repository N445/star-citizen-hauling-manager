<?php

declare(strict_types=1);

namespace App\Tests\Philosophy\Support;

final class IdSequence
{
    private int $next = 1;

    public function assign(object $entity): void
    {
        // Hors base, id() vaut null alors que le planificateur de soute indexe par id.
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $this->next++);
    }
}
