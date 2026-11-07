<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactCategory;
use App\Entity\ContactMessage;
use App\Entity\ReportStatus;
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
 * Messages de la page de contact : consultation seule ; le statut passe par {@see ContactMessageStatusController},
 * la suppression par l'action standard.
 *
 * @extends AbstractCrudController<ContactMessage>
 */
final class ContactMessageCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('contact.singular')
            ->setEntityLabelInPlural('contact.plural')
            ->setSearchFields(['message', 'replyEmail'])
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
            ->add(ChoiceFilter::new('status', 'contact.status')->setChoices($this->choices(ReportStatus::cases())))
            ->add(ChoiceFilter::new('category', 'contact.category')->setChoices($this->choices(ContactCategory::cases())))
            ->add(TextFilter::new('message', 'contact.message'))
            ->add(DateTimeFilter::new('createdAt', 'contact.created_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield DateTimeField::new('createdAt', 'contact.created_at');
        yield ComputedField::new('categoryLabel', 'contact.category', fn (ContactMessage $contact): string => $this->translator->trans($contact->category()->label()));
        yield ComputedField::new('statusLabel', 'contact.status', fn (ContactMessage $contact): string => $this->translator->trans($contact->status()->label()));
        yield ComputedField::new('authorLabel', 'contact.author', fn (ContactMessage $contact): string => $contact->author()?->email() ?? $this->translator->trans('tour.guest', [], 'admin'));
        yield TextField::new('replyEmail', 'contact.reply_email')->hideOnDetail();
        yield TextField::new('message', 'contact.message')->setMaxLength(90)->onlyOnIndex();
        yield Field::new('message', 'contact.message')->setLabel(false)->onlyOnDetail()->setTemplatePath('admin/contact/detail.html.twig');
    }

    /**
     * @param list<ReportStatus|ContactCategory> $cases
     *
     * @return array<string, string>
     */
    private function choices(array $cases): array
    {
        return array_combine(array_map(fn (ReportStatus|ContactCategory $case): string => $this->translator->trans($case->label()), $cases), array_map(static fn (ReportStatus|ContactCategory $case): string => $case->value, $cases));
    }
}
