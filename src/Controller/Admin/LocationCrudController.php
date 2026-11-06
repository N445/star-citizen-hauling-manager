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
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Arbre des lieux affiché en entier, dans l'ordre de l'arbre (systèmes, puis leurs enfants indentés).
 * Le paramètre d'URL `parentId` ne sert qu'à créer un lieu directement sous un autre (action « Ajouter un sous-lieu »).
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
            ->setEntityLabelInSingular('location.singular')
            ->setEntityLabelInPlural('location.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'location.plural')
            ->setPaginatorPageSize(500)
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
            ->add(Crud::PAGE_INDEX, Action::new('addChild', 'location.add_child', 'fa fa-plus')
                ->linkToUrl(fn (Location $location): string => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::NEW)
                    ->unset(EA::ENTITY_ID)
                    ->set(self::PARENT, $location->id())
                    ->generateUrl()))
            ->reorder(Crud::PAGE_INDEX, ['addChild', Action::EDIT, Action::DELETE])
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('location.add_system'));

        return $actions;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
    }

    /**
     * Remet les lignes dans l'ordre de l'arbre (le SQL ne sait pas trier par chemin) ; tout tient sur une page.
     */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_INDEX !== $responseParameters->get('pageName')) {
            return $responseParameters;
        }

        $rows = iterator_to_array($responseParameters->get('entities'), false);
        $names = static fn (EntityDto $row): array => array_map(static fn (Location $location): string => $location->name(), $row->getInstance()->path());
        usort($rows, static function (EntityDto $a, EntityDto $b) use ($names): int {
            foreach (array_map(null, $names($a), $names($b)) as [$left, $right]) {
                if ($left !== $right) {
                    return null === $left ? -1 : (null === $right ? 1 : strnatcasecmp($left, $right));
                }
            }

            return 0;
        });

        $responseParameters->set('entities', new EntityCollection(array_combine(
            array_map(static fn (EntityDto $row): string => $row->getPrimaryKeyValueAsString(), $rows),
            $rows,
        )));

        return $responseParameters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('tree', 'location.name')
            ->setVirtual(true)
            ->onlyOnIndex()
            ->setSortable(false)
            ->setTemplatePath('admin/location/_name.html.twig');
        yield TextField::new('name', 'location.name')->onlyOnForms()->setFormTypeOptions([
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
}
