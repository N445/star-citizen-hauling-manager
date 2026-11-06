<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DoorSide;
use App\Entity\Ship;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Ship>
 */
final class ShipCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Ship::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ship.singular')
            ->setEntityLabelInPlural('ship.plural')
            ->setSearchFields(['manufacturer', 'name', 'slug'])
            ->setDefaultSort(['manufacturer' => 'ASC', 'name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('manufacturer', 'ship.manufacturer'))
            ->add(TextFilter::new('name', 'ship.name'))
            ->add(NumericFilter::new('capacity', 'ship.capacity'))
            ->add(JsonContainsFilter::new('doorSides', 'ship.door_sides', $this->doorChoices()));
    }

    /**
     * @return array<string, string>
     */
    private function doorChoices(): array
    {
        return array_combine(
            array_map(fn (DoorSide $door): string => $this->translator->trans($door->label(), [], 'messages'), DoorSide::cases()),
            array_map(static fn (DoorSide $door): string => $door->value, DoorSide::cases()),
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('manufacturer', 'ship.manufacturer')->hideOnForm();
        yield TextField::new('name', 'ship.name')->hideOnForm();
        yield IntegerField::new('capacity', 'ship.capacity')->hideOnForm();
        yield ComputedField::new('doorLabels', 'ship.door_sides', $this->doorLabels(...));
        yield ChoiceField::new('doorSides', 'ship.door_sides')
            ->onlyOnForms()
            ->setChoices(array_combine(array_map(static fn (DoorSide $door): string => $door->label(), DoorSide::cases()), DoorSide::cases()))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setFormTypeOptions([
                'choice_translation_domain' => 'messages',
                'getter' => static fn (Ship $ship): array => $ship->doorSides(),
                'setter' => static fn (Ship $ship, array $doors) => $ship->changeDoors(array_values($doors)),
                'constraints' => [new Assert\Count(min: 1)],
            ]);
        yield CodeEditorField::new('cargoGroups', 'ship.cargo_groups')
            ->onlyOnDetail()
            ->setLanguage('js')
            ->formatValue(static fn (mixed $value, Ship $ship): string => (string) json_encode($ship->cargoGroups(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private function doorLabels(Ship $ship): string
    {
        return implode(', ', array_map(fn (DoorSide $door): string => $this->translator->trans($door->label(), [], 'messages'), $ship->doorSides()));
    }
}
