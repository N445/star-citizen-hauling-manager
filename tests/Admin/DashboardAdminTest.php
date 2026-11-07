<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\ContactCategory;
use App\Entity\ContactMessage;
use App\Entity\Delivery;
use App\Entity\Mission;
use App\Entity\MissionType;
use App\Entity\Tour;
use App\Entity\User;
use App\Service\Account\UserAccounts;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardAdminTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    private function admin(): User
    {
        $user = new User('admin@example.test');
        $user->grantAdmin();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        return $user;
    }

    private function tour(string $name, string $ship, ?User $owner, ?string $guestToken): Tour
    {
        $tour = new Tour($name, $ship, 96, null, null);
        $tour->addMission(new Mission('Run '.$name, 10_000, MissionType::Direct, ['Lorville'], [new Delivery('Area 18', 'Gold', 12)]));
        null === $owner ? $tour->entrustToGuest((string) $guestToken) : $tour->assignTo($owner);
        $this->entityManager->persist($tour);
        $this->entityManager->flush();

        return $tour;
    }

    private function contact(?User $author): ContactMessage
    {
        $contact = new ContactMessage($author, null === $author ? 'guest' : null, 'reply@example.test', ContactCategory::Improvement, 'Add a mining mode please', 'fr', new \DateTimeImmutable());
        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    public function testDashboardSummarisesEverything(): void
    {
        $admin = $this->admin();
        $this->tour('Mine', 'Drake Caterpillar', $admin, null);
        $this->tour('Theirs', 'Drake Caterpillar', null, 'guest-token');
        $this->contact(null);

        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Dashboard');
        self::assertSelectorTextContains('body', '1 with an account, 1 anonymous, 0 orphaned');
        self::assertSelectorTextContains('body', 'Drake Caterpillar');
        self::assertSelectorTextContains('body', 'Add a mining mode please');
    }

    public function testToursWithoutAccountShowAnonymousAuthor(): void
    {
        $admin = $this->admin();
        $this->tour('Mine', 'Hull A', $admin, null);
        $this->tour('Theirs', 'Hull A', null, 'guest-token');

        $this->client->request('GET', '/admin/tour');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'admin@example.test');
        self::assertSelectorTextContains('body', 'Anonymous');
    }

    public function testContactMessageCanBeReadAndHandled(): void
    {
        $this->admin();
        $id = $this->contact(null)->id();

        $crawler = $this->client->request('GET', '/admin/contact-message/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'reply@example.test');
        $token = $crawler->filter('form[action$="/resolve"] input[name=_token]')->attr('value');

        $this->client->request('POST', sprintf('/admin/contact-messages/%d/resolve', $id), ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', sprintf('/admin/contact-messages/%d/resolve', $id), ['_token' => $token]);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertTrue($this->entityManager->find(ContactMessage::class, $id)?->isResolved());
    }

    public function testDeletedAccountLeavesItsMessagesAnonymous(): void
    {
        $pilot = new User('pilot@example.test');
        $this->entityManager->persist($pilot);
        $this->entityManager->flush();
        $id = $this->contact($pilot)->id();
        $this->tour('Theirs', 'Hull A', $pilot, null);

        static::getContainer()->get(UserAccounts::class)->delete($pilot);
        $this->entityManager->clear();

        $contact = $this->entityManager->find(ContactMessage::class, $id);
        self::assertNotNull($contact);
        self::assertNull($contact->author());
        self::assertNull($contact->replyEmail());
        self::assertSame('Add a mining mode please', $contact->message());
        self::assertSame(0, $this->entityManager->getRepository(Tour::class)->count());

        $this->admin();
        $this->client->request('GET', '/admin/contact-message/'.$id);
        self::assertSelectorTextContains('body', 'Anonymous');
        self::assertSelectorTextNotContains('body', 'pilot@example.test');
    }

    public function testAdminDeletesAUserThroughTheSameRules(): void
    {
        $this->admin();
        $pilot = new User('pilot@example.test');
        $this->entityManager->persist($pilot);
        $this->entityManager->flush();
        $id = $this->contact($pilot)->id();
        $crawler = $this->client->request('GET', '/admin/user');
        $token = $crawler->filter('input[name=token]')->attr('value');

        $this->client->request('POST', '/admin/user/'.$pilot->id().'/delete', ['token' => $token]);
        $this->entityManager->clear();

        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'pilot@example.test']));
        self::assertNull($this->entityManager->find(ContactMessage::class, $id)?->replyEmail());
        self::assertNotNull($this->entityManager->find(ContactMessage::class, $id));
    }

    public function testNonAdminsAreKeptOut(): void
    {
        $user = new User('pilot@example.test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/contact-message');
        self::assertResponseStatusCodeSame(403);
    }
}
