<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tour;
use App\Entity\User;
use App\Form\Data\TourData;
use App\Form\TourFormType;
use App\Repository\ShipRepository;
use App\Repository\TourRepository;
use App\Security\GuestToken;
use App\Security\TourVoter;
use App\Service\Tour\TourWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TourController extends AbstractController
{
    public function __construct(
        private readonly TourWriter $tourWriter,
        private readonly TourRepository $tourRepository,
        private readonly ShipRepository $shipRepository,
        private readonly GuestToken $guestToken,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET', 'POST'])]
    public function home(Request $request, #[CurrentUser] ?User $user): Response
    {
        $current = null === $user ? $this->tourRepository->findOneOfGuest($this->guestToken->current()) : $this->tourRepository->findLatestOwnedBy($user);

        if (null === $current) {
            return $this->new($request, $user);
        }

        return $this->edit($request, $current, 'home');
    }

    #[Route('/tours', name: 'tour_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('tour/index.html.twig', ['tours' => $this->tourRepository->findOwnedBy($user)]);
    }

    #[Route('/tours/new', name: 'tour_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] ?User $user): Response
    {
        $data = new TourData();
        $tourForm = $this->createForm(TourFormType::class, $data)->handleRequest($request);

        if (!$tourForm->isSubmitted() || !$tourForm->isValid()) {
            return $this->page(null, $tourForm);
        }

        $tour = null === $user ? $this->tourWriter->createForGuest($data, $this->guestToken->currentOrNew()) : $this->tourWriter->create($data, $user);
        $this->addFlash('success', 'flash.tour.created');

        return null === $user ? $this->redirectToRoute('home') : $this->redirectToRoute('tour', ['id' => $tour->id()]);
    }

    #[IsGranted(TourVoter::ACCESS, subject: 'tour')]
    #[Route('/tours/{id}', name: 'tour', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function tour(Request $request, Tour $tour): Response
    {
        return $this->edit($request, $tour, 'tour');
    }

    private function edit(Request $request, Tour $tour, string $route): Response
    {
        $data = TourData::fromTour($tour);
        $tourForm = $this->createForm(TourFormType::class, $data)->handleRequest($request);

        if (!$tourForm->isSubmitted() || !$tourForm->isValid()) {
            return $this->page($tour, $tourForm);
        }

        $this->tourWriter->update($tour, $data);
        $this->addFlash('success', 'flash.tour.saved');

        return $this->redirectToRoute($route, 'tour' === $route ? ['id' => $tour->id()] : []);
    }

    private function page(?Tour $tour, FormInterface $tourForm): Response
    {
        return $this->render('tour/page.html.twig', [
            'tour' => $tour,
            'tourForm' => $tourForm,
            'ship' => $this->shipRepository->findOneByFullName((string) $tourForm->get('shipName')->getData()),
        ]);
    }
}
