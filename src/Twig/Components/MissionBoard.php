<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Mission;
use App\Entity\Tour;
use App\Form\Data\MissionData;
use App\Form\MissionFormType;
use App\Security\TourVoter;
use App\Service\Tour\MissionWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class MissionBoard extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public Tour $tour;

    #[LiveProp]
    public ?Mission $editedMission = null;

    /** Change à chaque formulaire neuf : les champs Tom Select, ignorés au re-rendu, sont alors recréés. */
    #[LiveProp]
    public int $formVersion = 0;

    /** Retour visuel du dernier enregistrement : valable pour ce seul rendu. */
    public ?int $flashedMissionId = null;

    public function __construct(
        private readonly MissionWriter $missionWriter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[PostHydrate]
    public function denyForeignTour(): void
    {
        $this->denyAccessUnlessGranted(TourVoter::ACCESS, $this->tour);
    }

    #[LiveListener('progressChanged')]
    public function refresh(): void
    {
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();
        $this->missionWriter->save($this->tour, $this->editedMission, $this->getForm()->getData());
        $this->flashedMissionId = $this->editedMission?->id() ?? ($this->tour->missions()->last() ?: null)?->id();
        $this->toast(null === $this->editedMission ? 'mission.board.added' : 'mission.board.saved');
        $this->emit('missionsChanged');
        $this->restart(null);
    }

    #[LiveAction]
    public function edit(#[LiveArg] int $id): void
    {
        $this->restart($this->missionOfTour($id));
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->restart(null);
    }

    #[LiveAction]
    public function remove(#[LiveArg] int $id): void
    {
        $this->missionWriter->remove($this->missionOfTour($id));
        $this->toast('mission.board.removed');
        $this->emit('missionsChanged');
        $this->restart(null);
    }

    protected function instantiateForm(): FormInterface
    {
        $data = null === $this->editedMission ? MissionData::blank() : MissionData::fromMission($this->editedMission);

        return $this->createForm(MissionFormType::class, $data);
    }

    private function restart(?Mission $mission): void
    {
        $this->editedMission = $mission;
        ++$this->formVersion;
        $this->isValidated = false;
        $this->validatedFields = [];
        $this->resetForm();
    }

    private function toast(string $message): void
    {
        $this->dispatchBrowserEvent('toast:show', ['message' => $this->translator->trans($message), 'variant' => 'success']);
    }

    private function missionOfTour(int $id): Mission
    {
        return $this->tour->mission($id);
    }
}
