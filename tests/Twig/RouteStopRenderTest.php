<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Tour;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteStopRenderTest extends WebTestCase
{
    #[Test]
    public function tourPageRendersQuantityBadgesOnEveryStop(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $user = new User('route-render@example.test');
        $entityManager->persist($user);
        $tour = new Tour('Render', 'Caterpillar', 576, null, 'Lorville');
        $tour->assignTo($user);
        $tour->addMission(new Mission('Direct', 1000, MissionType::from('direct'), ['Lorville'], [new Delivery('Area18', 'Laranite', 11)]));
        $tour->addMission(new Mission('Multi', 2000, MissionType::from('multiple_pick_up'), ['Lorville', 'Area18', 'Orison'], [new Delivery('Everus Harbor', 'Gold', 11)]));
        $entityManager->persist($tour);
        $entityManager->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/tours/'.$tour->id());

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(4, $crawler->filter('.scu-edit__badge')->count());
        self::assertStringContainsString('11 SCU', $crawler->filter('.scu-edit__badge')->first()->text());
    }
}
