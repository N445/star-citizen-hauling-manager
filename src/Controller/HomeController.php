<?php

declare(strict_types=1);

namespace App\Controller;

use App\Twig\Catalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function __invoke(): Response
    {
        return $this->render('home/index.html.twig', ['catalog' => Catalog::json()]);
    }
}
