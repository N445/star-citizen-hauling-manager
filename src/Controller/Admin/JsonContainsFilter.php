<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;

/**
 * Filtre « contient / ne contient pas » sur une colonne JSON de chaînes (rôles, portes cargo) ;
 * l'ArrayFilter d'EasyAdmin 5.9 plante à la soumission (clé « value » absente dans son transformateur).
 */
final class JsonContainsFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param array<string, string> $choices libellé => valeur stockée
     */
    public static function new(string $propertyName, string $label, array $choices): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle')
            ->setFormTypeOption('value_type_options.choices', $choices);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $operator = ComparisonType::NEQ === $filterDataDto->getComparison() ? 'NOT LIKE' : 'LIKE';

        $queryBuilder
            ->andWhere(sprintf("CONCAT(%s.%s, '') %s :%s", $filterDataDto->getEntityAlias(), $filterDataDto->getProperty(), $operator, $filterDataDto->getParameterName()))
            ->setParameter($filterDataDto->getParameterName(), '%"'.$filterDataDto->getValue().'"%');
    }
}
