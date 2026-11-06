<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tour;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Tour>
 */
final class TourCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Tour::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('tour.singular')
            ->setEntityLabelInPlural('tour.plural')
            ->setSearchFields(['name', 'shipName'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'tour.name'))
            ->add(EntityFilter::new('owner', 'tour.owner'))
            ->add(NullFilter::new('guestToken', 'tour.filter.guest')->setChoiceLabels('tour.filter.not_guest', 'tour.filter.is_guest'))
            ->add(TextFilter::new('shipName', 'tour.ship'))
            ->add(NumericFilter::new('capacity', 'tour.capacity'))
            ->add(TextFilter::new('startLocation', 'tour.start_location'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name', 'tour.name');
        yield ComputedField::new('ownerLabel', 'tour.owner', $this->ownerLabel(...));
        yield TextField::new('shipName', 'tour.ship');
        yield IntegerField::new('capacity', 'tour.capacity');
        yield TextField::new('startLocation', 'tour.start_location');
        yield ComputedField::new('missionCount', 'tour.missions', static fn (Tour $tour): int => $tour->missions()->count())->hideOnDetail();
        yield Field::new('missions')->setLabel(false)->onlyOnDetail()->setTemplatePath('admin/tour/missions.html.twig');
    }

    private function ownerLabel(Tour $tour): string
    {
        $owner = $tour->owner();

        if (null !== $owner) {
            return $owner->email();
        }

        return $this->translator->trans($tour->isGuestTour() ? 'tour.guest' : 'tour.orphan', [], 'admin');
    }
}
