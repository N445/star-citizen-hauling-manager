<?php

declare(strict_types=1);

namespace App\Form;

use App\Catalog\LocationCatalog;
use App\Entity\DoorSide;
use App\Entity\Ship;
use App\Form\Data\TourData;
use App\Repository\ShipRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TourFormType extends AbstractType
{
    public function __construct(
        private readonly ShipRepository $shipRepository,
        private readonly LocationCatalog $locationCatalog,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'tour.form.name',
            ])
            ->add('shipName', TextType::class, [
                'label' => 'tour.form.ship',
                'attr' => SearchableInput::attributes($this->shipSuggestions()) + [
                    'data-action' => 'tom-select:change->ship-capacity#fill tom-select:change->door-sides#fill',
                    'data-ship-name' => '',
                ],
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'tour.form.capacity',
                'attr' => ['min' => 1, 'data-ship-capacity-target' => 'capacity'],
            ])
            ->add('startLocation', TextType::class, [
                'label' => 'tour.form.start_location',
                'required' => false,
                'attr' => SearchableInput::locations($this->locationCatalog) + ['placeholder' => 'form.optional'],
            ])
            ->add('doorSides', EnumType::class, [
                'label' => 'tour.form.door_sides',
                'class' => DoorSide::class,
                'choice_label' => static fn (DoorSide $side): string => $side->label(),
                'multiple' => true,
                'expanded' => true,
                'label_attr' => ['class' => 'checkbox-inline'],
                'choice_attr' => static fn (): array => ['data-door-sides-target' => 'input', 'data-action' => 'door-sides#emit'],
                'help' => 'tour.form.door_sides_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TourData::class]);
    }

    /**
     * @return list<array{value: string, text: string, capacity: int, cargoGroups: list<array<string, mixed>>, doorSides: list<string>}>
     */
    private function shipSuggestions(): array
    {
        return array_map(
            static fn (Ship $ship): array => [
                'value' => $ship->name(),
                'text' => $ship->name(),
                'capacity' => $ship->capacity(),
                'cargoGroups' => $ship->cargoGroups(),
                'doorSides' => array_map(static fn (DoorSide $side): string => $side->value, $ship->doorSides()),
            ],
            $this->shipRepository->findAllSorted(),
        );
    }
}
