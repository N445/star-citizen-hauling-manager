<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\Account\AccountRules;
use App\Service\Account\UserAccounts;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserAccounts $userAccounts,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('user.singular')
            ->setEntityLabelInPlural('user.plural')
            ->setSearchFields(['email'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email', 'user.email'))
            ->add(JsonContainsFilter::new('roles', 'user.admin', ['user.admin' => User::ADMIN_ROLE]))
            ->add(DateTimeFilter::new('registeredAt', 'user.registered_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'user.email')->setFormTypeOptions([
            'getter' => static fn (User $user): string => $user->email(),
            'setter' => static fn (User $user, ?string $email) => $user->changeEmail((string) $email),
            'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
        ]);
        yield BooleanField::new('admin', 'user.admin')->renderAsSwitch(false)->setFormTypeOptions([
            'getter' => static fn (User $user): bool => $user->isAdmin(),
            'setter' => static fn (User $user, bool $admin) => $admin ? $user->grantAdmin() : $user->revokeAdmin(),
        ]);
        yield DateTimeField::new('registeredAt', 'user.registered_at')->hideOnForm();
        yield ComputedField::new('tours', 'user.tours', fn (User $user): int => $this->userAccounts->ownedTourCount($user));
        yield TextField::new('plainPassword', 'user.new_password')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setHelp('user.new_password_help')
            ->setFormTypeOptions([
                'required' => false,
                'getter' => static fn (): ?string => null,
                'setter' => $this->changePassword(...),
                'constraints' => [new Assert\Length(min: AccountRules::PASSWORD_MIN_LENGTH, max: 4096)],
            ]);
    }

    /**
     * Même suppression que le profil et la console : tournées effacées, messages de contact rendus anonymes.
     */
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $this->userAccounts->delete($entityInstance);
    }

    private function changePassword(User $user, ?string $plainPassword): void
    {
        if (null === $plainPassword || '' === $plainPassword) {
            return;
        }

        $user->changePassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
