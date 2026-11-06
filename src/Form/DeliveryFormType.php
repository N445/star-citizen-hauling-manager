<?php

declare(strict_types=1);

namespace App\Form;

use App\Catalog\LocationCatalog;
use App\Entity\Commodity;
use App\Form\Data\DeliveryData;
use App\Repository\CommodityRepository;
use App\Service\CommodityColors;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DeliveryFormType extends AbstractType
{
    public function __construct(
        private readonly CommodityRepository $commodityRepository,
        private readonly LocationCatalog $locationCatalog,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dropoff', TextType::class, [
                'label' => 'mission.form.dropoff',
                'attr' => SearchableInput::locations($this->locationCatalog) + ['placeholder' => 'mission.form.dropoff_placeholder'],
            ])
            ->add('resource', TextType::class, [
                'label' => 'mission.form.resource',
                'attr' => SearchableInput::attributes($this->commoditySuggestions(), badges: true) + [
                    'data-tom-select-fallback-color-value' => CommodityColors::UNKNOWN,
                    'placeholder' => 'mission.form.resource',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'mission.form.quantity',
                'attr' => ['min' => 1, 'placeholder' => 'mission.form.quantity'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DeliveryData::class]);
    }

    /**
     * @return list<array{value: string, text: string, color: string}>
     */
    private function commoditySuggestions(): array
    {
        return array_map(
            static fn (Commodity $commodity): array => ['value' => $commodity->name(), 'text' => $commodity->name(), 'color' => $commodity->color()],
            $this->commodityRepository->findAllSorted(),
        );
    }
}
