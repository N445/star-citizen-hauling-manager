<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

final class ComputedField
{
    /**
     * @param \Closure(object): (string|int|null) $compute
     */
    public static function new(string $name, string $label, \Closure $compute): Field
    {
        // Le modèle explicite évite le badge « Inaccessible » d'EasyAdmin sur une propriété absente de l'entité.
        return Field::new($name, $label)
            ->setVirtual(true)
            ->hideOnForm()
            ->setSortable(false)
            ->setTemplatePath('@EasyAdmin/crud/field/generic.html.twig')
            ->formatValue(static fn (mixed $value, object $entity): string|int|null => self::escaped($compute($entity)));
    }

    private static function escaped(string|int|null $value): string|int|null
    {
        return \is_string($value) ? htmlspecialchars($value, \ENT_QUOTES) : $value;
    }
}
