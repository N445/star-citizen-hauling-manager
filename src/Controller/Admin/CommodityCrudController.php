<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Commodity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractCrudController<Commodity>
 */
final class CommodityCrudController extends AbstractCrudController
{
    private const string DEFAULT_COLOR = '#6c757d';

    public static function getEntityFqcn(): string
    {
        return Commodity::class;
    }

    public function createEntity(string $entityFqcn): Commodity
    {
        return new Commodity('', self::DEFAULT_COLOR);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('commodity.singular')
            ->setEntityLabelInPlural('commodity.plural')
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'commodity.name'))
            ->add(TextFilter::new('color', 'commodity.color'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'commodity.name')->setFormTypeOptions([
            'getter' => static fn (Commodity $commodity): string => $commodity->name(),
            'setter' => static fn (Commodity $commodity, ?string $name) => $commodity->rename((string) $name),
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
        ]);
        yield ColorField::new('color', 'commodity.color')->setFormTypeOptions([
            'getter' => static fn (Commodity $commodity): string => $commodity->color(),
            'setter' => static fn (Commodity $commodity, ?string $color) => $commodity->recolor((string) $color),
            'constraints' => [new Assert\NotBlank(), new Assert\CssColor(formats: Assert\CssColor::HEX_LONG)],
        ]);
    }
}
