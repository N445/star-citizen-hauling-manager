<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReportCategory;
use App\Entity\ReportStatus;
use App\Entity\RouteReport;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Signalements d'erreur sur une route : consultation seule (l'instantané ne doit jamais changer) ; le statut passe par
 * {@see RouteReportStatusController}, la suppression par l'action standard.
 *
 * @extends AbstractCrudController<RouteReport>
 */
final class RouteReportCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return RouteReport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('report.singular')
            ->setEntityLabelInPlural('report.plural')
            ->setSearchFields(['message'])
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
            ->add(ChoiceFilter::new('status', 'report.status')->setChoices($this->choices(ReportStatus::cases())))
            ->add(ChoiceFilter::new('category', 'report.category')->setChoices($this->choices(ReportCategory::cases())))
            ->add(TextFilter::new('message', 'report.message'))
            ->add(DateTimeFilter::new('createdAt', 'report.created_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield DateTimeField::new('createdAt', 'report.created_at');
        yield ComputedField::new('categoryLabel', 'report.category', fn (RouteReport $report): string => $this->translator->trans($report->category()->label()));
        yield ComputedField::new('statusLabel', 'report.status', fn (RouteReport $report): string => $this->translator->trans($report->status()->label()));
        yield ComputedField::new('authorLabel', 'report.author', fn (RouteReport $report): string => $this->authorLabel($report));
        yield ComputedField::new('tourLabel', 'report.tour', fn (RouteReport $report): string => $report->tour()?->name() ?? $this->translator->trans('report.tour_deleted', [], 'admin'))->hideOnDetail();
        yield TextField::new('message', 'report.message')->setMaxLength(90)->onlyOnIndex();
        yield Field::new('snapshot')->setLabel(false)->onlyOnDetail()->setTemplatePath('admin/report/detail.html.twig');
    }

    /**
     * @param list<ReportStatus|ReportCategory> $cases
     *
     * @return array<string, string>
     */
    private function choices(array $cases): array
    {
        return array_combine(array_map(fn (ReportStatus|ReportCategory $case): string => $this->translator->trans($case->label()), $cases), array_map(static fn (ReportStatus|ReportCategory $case): string => $case->value, $cases));
    }

    private function authorLabel(RouteReport $report): string
    {
        return $report->author()?->email() ?? $this->translator->trans('tour.guest', [], 'admin');
    }
}
