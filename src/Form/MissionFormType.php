<?php

declare(strict_types=1);

namespace App\Form;

use App\Catalog\LocationCatalog;
use App\Entity\MissionType;
use App\Form\Data\MissionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

final class MissionFormType extends AbstractType
{
    private const string SEPARATOR = ',';

    public function __construct(private readonly LocationCatalog $locationCatalog)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'label' => 'mission.form.type',
                'class' => MissionType::class,
                'choice_label' => static fn (MissionType $type): string => $type->label(),
                'placeholder' => 'mission.form.type_placeholder',
                'help' => 'mission.form.type_help',
            ])
            ->add('name', TextType::class, [
                'label' => 'mission.form.name',
                'required' => false,
                'attr' => ['placeholder' => 'form.optional'],
            ])
            ->add('reward', IntegerType::class, [
                'label' => 'mission.form.reward',
                'attr' => ['min' => 0, 'placeholder' => 'mission.form.reward_placeholder'],
            ])
            ->add('pickups', TextType::class, [
                'label' => 'mission.form.pickups',
                'attr' => SearchableInput::locations($this->locationCatalog, multiple: true),
            ])
            ->add('deliveries', LiveCollectionType::class, [
                'label' => false,
                'entry_type' => DeliveryFormType::class,
                'entry_options' => ['label' => false],
                'button_add_options' => ['label' => 'mission.form.add_delivery', 'attr' => ['class' => 'btn btn-sm btn-outline-secondary']],
                'button_delete_options' => ['label' => '×', 'attr' => ['class' => 'btn btn-sm btn-outline-danger', 'title' => 'mission.form.remove_delivery']],
            ]);

        $builder->get('pickups')->addModelTransformer(new CallbackTransformer(
            static fn (?array $pickups): string => implode(self::SEPARATOR, $pickups ?? []),
            static fn (?string $pickups): array => array_values(array_filter(array_map(trim(...), explode(self::SEPARATOR, $pickups ?? '')))),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MissionData::class]);
    }
}
