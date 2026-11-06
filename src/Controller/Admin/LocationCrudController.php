<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Repository\LocationRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Arbre des lieux, un niveau à la fois : d'abord les systèmes (lieux sans parent), puis les enfants du lieu ouvert.
 * Le niveau affiché suit le paramètre d'URL `parentId`, qu'EasyAdmin conserve dans ses liens et redirections.
 *
 * @extends AbstractCrudController<Location>
 */
final class LocationCrudController extends AbstractCrudController
{
    private const string PARENT = 'parentId';

    public function __construct(
        private readonly LocationRepository $locationRepository,
        private readonly RequestStack $requestStack,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Location::class;
    }

    public function createEntity(string $entityFqcn): Location
    {
        return new Location('', $this->currentParent());
    }

    public function configureCrud(Crud $crud): Crud
    {
        $parent = $this->currentParent();

        return $crud
            ->setEntityLabelInSingular(null === $parent ? 'location.system' : 'location.singular')
            ->setEntityLabelInPlural(null === $parent ? 'location.systems' : 'location.plural')
            ->setPageTitle(Crud::PAGE_INDEX, null === $parent ? 'location.systems' : (string) $parent)
            ->setPageTitle(Crud::PAGE_NEW, null === $parent ? 'location.new_system' : $this->translator->trans('location.new_in', ['parent' => (string) $parent], 'admin'))
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Location $location): string => (string) $location)
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::new('open', 'location.open', 'fa fa-folder-open')
                ->linkToUrl(fn (Location $location): string => $this->levelUrl($location->id())))
            ->reorder(Crud::PAGE_INDEX, ['open', Action::EDIT, Action::DELETE])
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action): Action => $action->setLabel(
                null === $this->currentParent() ? 'location.add_system' : 'location.add',
            ));

        $parent = $this->currentParent();

        if (null !== $parent) {
            $actions->add(Crud::PAGE_INDEX, Action::new('up', $parent->parent()?->name() ?? 'location.systems', 'fa fa-arrow-up')
                ->linkToUrl($this->levelUrl($parent->parent()?->id()))
                ->createAsGlobalAction());
        }

        return $actions;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $parent = $this->currentParent();

        return null === $parent
            ? $queryBuilder->andWhere('entity.parent IS NULL')
            : $queryBuilder->andWhere('entity.parent = :parent')->setParameter('parent', $parent);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'location.name')->setFormTypeOptions([
            'getter' => static fn (Location $location): string => $location->name(),
            'setter' => static fn (Location $location, ?string $name) => $location->rename((string) $name),
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
        ]);
        yield ComputedField::new('childCount', 'location.children', static fn (Location $location): int => \count($location->children()));
    }

    private function currentParent(): ?Location
    {
        $id = $this->requestStack->getCurrentRequest()?->query->getInt(self::PARENT);

        return $id > 0 ? $this->locationRepository->find($id) : null;
    }

    private function levelUrl(?int $parentId): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset(EA::ENTITY_ID)
            ->unset(EA::PAGE)
            ->unset(EA::QUERY)
            ->unset(EA::FILTERS)
            ->unset(EA::SORT)
            ->set(self::PARENT, $parentId)
            ->generateUrl();
    }
}
