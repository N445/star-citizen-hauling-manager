<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Location;

final class LocationTreeControllerTest extends LocationTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/locations');

        self::assertResponseRedirects();
    }

    public function testNonAdminIsForbidden(): void
    {
        $user = new \App\Entity\User('user@example.test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/locations');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/locations/add', ['names' => 'X', '_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRootPageShowsTheWholeTree(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $this->child($hurston, 'Lorville');
        $this->system('Pyro');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[role="tree"]'));
        self::assertSame(2, $crawler->filter('[role="tree"] > li[role="treeitem"]')->count());
        self::assertCount(4, $crawler->filter('li[role="treeitem"]'));
        self::assertSame('false', $crawler->filter('li[data-name="Hurston"]')->attr('aria-expanded'));
        self::assertSame('true', $crawler->filter('li[data-name="Stanton"]')->attr('aria-expanded'));
        self::assertNull($crawler->filter('li[data-name="Lorville"]')->attr('aria-expanded'));
        self::assertCount(1, $crawler->filter('[data-loc-filter]'));
        self::assertCount(1, $crawler->filter('form[action$="/admin/locations/add"] textarea[name="names"]'));
        self::assertSame('', $crawler->filter('form[action$="/admin/locations/add"] input[name="parent"]')->attr('value'));
    }

    public function testLocationPageShowsBreadcrumbFactsTreeAndForms(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $aberdeen = $this->child($hurston, 'Aberdeen');
        $this->child($aberdeen, 'Outpost');
        $this->child($hurston, 'Lorville');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$hurston->id());

        self::assertResponseIsSuccessful();
        $crumbs = $crawler->filter('.loc-breadcrumb li');
        self::assertSame(['Tous les systèmes', 'Stanton', 'Hurston'], array_map('trim', $crumbs->each(static fn ($node): string => $node->text())));
        self::assertSame('Hurston', trim($crawler->filter('.breadcrumb-item.active')->text()));
        self::assertStringContainsString('Stanton › Hurston', $crawler->filter('.loc-facts')->text());
        self::assertStringContainsString('3 lieux en dessous', $crawler->filter('.loc-facts')->text());
        self::assertSame(['Aberdeen', 'Outpost', 'Lorville'], $this->treeNames($crawler));
        self::assertSame($hurston->id().'', $this->parentOfAddForm($crawler));
        self::assertCount(1, $crawler->filter(sprintf('form[action$="/admin/locations/%d/rename"] input[name="name"]', $hurston->id())));
        self::assertCount(1, $crawler->filter(sprintf('form[action$="/admin/locations/%d/move"] select[name="parent"]', $hurston->id())));
        self::assertCount(1, $crawler->filter(sprintf('form[action$="/admin/locations/%d/delete"]', $hurston->id())));
        self::assertStringContainsString('3 sous-lieux seront supprimés', $crawler->filter('[role="alert"]')->text());
        self::assertCount(4, $crawler->filter('input[name="_token"]'));
        // le lieu et ses descendants ne sont pas proposés comme nouveau parent
        $options = $crawler->filter('select[name="parent"] option')->each(static fn ($node): string => $node->text());
        self::assertSame(['Aucun parent (en faire un système)', 'Stanton'], array_map('trim', $options));
    }

    public function testWritesRequireAValidCsrfToken(): void
    {
        $this->system('Stanton');
        $this->loginAsAdmin();

        $this->client->request('POST', '/admin/locations/add', ['names' => 'Pyro', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->entityManager->getRepository(Location::class)->findAll());
    }

    public function testAddingSeveralLocationsFromThePage(): void
    {
        $stanton = $this->system('Stanton');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$stanton->id());
        $form = $crawler->filter('form[action$="/admin/locations/add"]')->form(['names' => "Hurston\nArcCorp"]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/locations/'.$stanton->id());
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('2 lieux ajoutés', $crawler->filter('#flash-messages')->text());
        self::assertSame(['ArcCorp', 'Hurston'], $this->childNames($this->fresh($stanton)));
    }

    public function testDuplicateNameShowsTheFrenchMessageAndKeepsTheInput(): void
    {
        $stanton = $this->system('Stanton');
        $this->child($stanton, 'Hurston');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$stanton->id());
        $this->client->submit($crawler->filter('form[action$="/admin/locations/add"]')->form(['names' => "ArcCorp\nhurston"]));
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Un lieu nommé « hurston » existe déjà ici.', $crawler->filter('#flash-messages')->text());
        self::assertSame("ArcCorp\nhurston", $crawler->filter('#loc-add-names')->getNode(0)->textContent);
        self::assertSame(['Hurston'], $this->childNames($this->fresh($stanton)));
    }

    public function testErrorMessagesAreEscaped(): void
    {
        $stanton = $this->system('Stanton');
        $this->child($stanton, '<b>x</b>');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$stanton->id());
        $this->client->submit($crawler->filter('form[action$="/admin/locations/add"]')->form(['names' => '<b>X</b>']));
        $crawler = $this->client->followRedirect();

        self::assertCount(0, $crawler->filter('#flash-messages b'));
        self::assertStringContainsString('<b>X</b>', $crawler->filter('#flash-messages')->text());
    }

    public function testRenameMoveAndDeleteFromThePage(): void
    {
        $stanton = $this->system('Stanton');
        $pyro = $this->system('Pyro');
        $hurston = $this->child($stanton, 'Hurston');
        $this->child($hurston, 'Lorville');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$hurston->id());
        $this->client->submit($crawler->filter('form[action$="/rename"]')->form(['name' => 'Hurston II']));
        $crawler = $this->client->followRedirect();
        self::assertSame('Hurston II', $this->fresh($hurston)->name());

        $this->client->submit($crawler->filter('form[action$="/move"]')->form(['parent' => (string) $pyro->id()]));
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Pyro › Hurston II', $crawler->filter('.loc-facts')->text());
        self::assertSame([], $this->fresh($stanton)->children());

        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects('/admin/locations/'.$pyro->id());
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('1 sous-lieu supprimé', $crawler->filter('#flash-messages')->text());
        $this->entityManager->clear();
        self::assertCount(2, $this->entityManager->getRepository(Location::class)->findAll());
    }

    public function testDeletingASystemReturnsToTheRoot(): void
    {
        $stanton = $this->system('Stanton');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$stanton->id());
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());

        self::assertResponseRedirects('/admin/locations');
    }

    public function testMovingUnderADescendantIsRefusedWithAMessage(): void
    {
        $stanton = $this->system('Stanton');
        $hurston = $this->child($stanton, 'Hurston');
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations/'.$stanton->id());
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/locations/'.$stanton->id().'/move', ['parent' => (string) $hurston->id(), '_token' => $token]);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Impossible de déplacer', $crawler->filter('#flash-messages')->text());
        self::assertTrue($this->fresh($stanton)->isSystem());
    }

    public function testMenuEntryLeadsToTheRoot(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/locations');
        self::assertCount(1, $crawler->filter('a[href$="/admin/locations"]'));
    }

    private function fresh(Location $location): Location
    {
        return $this->entityManager->find(Location::class, $location->id());
    }

    /** @return list<string> */
    private function childNames(Location $location): array
    {
        return array_map(static fn (Location $child): string => $child->name(), $location->children());
    }

    /** @return list<string> */
    private function treeNames(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return array_map('trim', $crawler->filter('[role="tree"] .loc-name')->each(static fn ($node): string => $node->text()));
    }

    private function parentOfAddForm(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        return (string) $crawler->filter('form[action$="/admin/locations/add"] input[name="parent"]')->attr('value');
    }
}
