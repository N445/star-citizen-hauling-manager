<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ContactMessage;
use App\Entity\User;
use App\Security\GuestToken;
use App\Service\Contact\ContactSender;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class PublicPagesTest extends WebTestCase
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

    private function sendContact(string $message, string $category = 'improvement', string $email = ''): void
    {
        $this->client->request('GET', '/contact');
        $this->client->submitForm('Send', [
            'contact_form[category]' => $category,
            'contact_form[message]' => $message,
            'contact_form[replyEmail]' => $email,
        ]);
    }

    /**
     * @return list<ContactMessage>
     */
    private function contacts(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(ContactMessage::class)->findAll();
    }

    public function testAnonymousVisitorSendsAMessage(): void
    {
        $this->sendContact('Please add a mining mode to the planner', 'improvement', ' Pilot@Example.TEST ');

        self::assertResponseRedirects('/contact');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Your message has been sent');
        [$contact] = $this->contacts();
        self::assertNull($contact->author());
        self::assertSame('pilot@example.test', $contact->replyEmail());
        self::assertSame('Please add a mining mode to the planner', $contact->message());
        self::assertSame('en', $contact->locale());
        self::assertNotNull($this->client->getCookieJar()->get(GuestToken::COOKIE));
    }

    public function testSignedInUserIsTheAuthorAndEmailIsPrefilled(): void
    {
        $user = new User('pilot@example.test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/contact');
        self::assertSame('pilot@example.test', $crawler->filter('#contact_form_replyEmail')->attr('value'));
        $this->sendContact('The cargo grid of the Hull A looks wrong', 'bug', '');

        [$contact] = $this->contacts();
        self::assertSame('pilot@example.test', $contact->author()?->email());
        self::assertNull($contact->replyEmail());
    }

    public function testInvalidMessageIsRefused(): void
    {
        $this->sendContact('short');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Your message is a bit short (10 characters minimum).');
        self::assertSame([], $this->contacts());
    }

    public function testSecondMessageTooSoonIsRefused(): void
    {
        $this->client->getCookieJar()->set(new Cookie(GuestToken::COOKIE, 'guest-token'));
        $this->sendContact('First message about the route');
        $this->sendContact('Second message about the route');

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('body', 'before sending another message');
        self::assertCount(1, $this->contacts());
        self::assertSame(60, ContactSender::MIN_SECONDS_BETWEEN);
    }

    public function testLegalPagesAreLinkedFromTheFooterInBothLanguages(): void
    {
        $crawler = $this->client->request('GET', '/legal');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Legal notice');
        self::assertSelectorTextContains('body', 'N445');
        self::assertSelectorTextContains('body', 'Personal server of the publisher, located in France.');
        self::assertCount(1, $crawler->filter('footer a[href="/privacy"]'));
        self::assertCount(1, $crawler->filter('footer a[href="/contact"]'));

        $this->client->request('GET', '/locale/fr');
        $this->client->request('GET', '/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorTextContains('body', 'hauling_guest');
        self::assertSelectorTextContains('body', 'Cloudflare Turnstile');
    }

    public function testSitemapListsOnlyPublicPages(): void
    {
        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=utf-8');
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        $locations = array_map(static fn (\SimpleXMLElement $url): string => (string) $url->loc, iterator_to_array($xml->url, false));
        sort($locations);
        self::assertSame(['http://localhost/', 'http://localhost/contact', 'http://localhost/legal', 'http://localhost/login', 'http://localhost/privacy', 'http://localhost/register'], $locations);
    }

    public function testRobotsPointsToTheSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Disallow: /admin', (string) $this->client->getResponse()->getContent());
    }
}
