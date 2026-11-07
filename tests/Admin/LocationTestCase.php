<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Location;
use App\Entity\User;
use App\Service\Location\LocationEditor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Noyau + base SQLite en mémoire (schéma neuf à chaque test), un seul noyau pour toutes les requêtes du test.
 */
abstract class LocationTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected LocationEditor $editor;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($this->entityManager);
        $tool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->editor = new LocationEditor($this->entityManager, $this->entityManager->getRepository(Location::class));
    }

    protected function system(string $name): Location
    {
        return $this->editor->add(null, $name);
    }

    protected function child(Location $parent, string $name): Location
    {
        return $this->editor->add($parent, $name);
    }

    protected function loginAsAdmin(): User
    {
        $user = new User('admin@example.test');
        $user->grantAdmin();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/locale/fr');

        return $user;
    }
}
