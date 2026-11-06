<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\StatsProxyController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class StatsProxyControllerTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $upstreamCalls = [];

    public function testDisabledWithoutConfiguration(): void
    {
        $proxy = $this->proxy('', '');

        self::assertSame(404, $proxy->script()->getStatusCode());
        self::assertSame(404, $proxy->collect($this->collectRequest('site'))->getStatusCode());
        self::assertSame([], $this->upstreamCalls);
    }

    public function testScriptIsFetchedFromUmamiWithItsEndpointRenamed(): void
    {
        $response = $this->proxy('https://umami.example/', 'site')->script();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('${host}/c`', (string) $response->getContent());
        self::assertStringNotContainsString('/api/send', (string) $response->getContent());
        self::assertSame('https://umami.example/script.js', $this->upstreamCalls[0]['url']);
    }

    public function testEventForAnotherWebsiteIsRefused(): void
    {
        $response = $this->proxy('https://umami.example', 'site')->collect($this->collectRequest('someone-else'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->upstreamCalls);
    }

    public function testEventIsForwardedWithTheVisitorAddressAndAgent(): void
    {
        $request = $this->collectRequest('site');
        $request->headers->set('X-Forwarded-For', '203.0.113.9, 172.18.0.2');
        $request->headers->set('User-Agent', 'TestBrowser/1.0');

        $response = $this->proxy('https://umami.example', 'site')->collect($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"cache":"abc"}', $response->getContent());
        self::assertSame('POST', $this->upstreamCalls[0]['method']);
        self::assertSame('https://umami.example/api/send', $this->upstreamCalls[0]['url']);
        $sent = implode("\n", $this->upstreamCalls[0]['options']['headers']);
        self::assertStringContainsString('X-Forwarded-For: 172.18.0.2', $sent);
        self::assertStringContainsString('User-Agent: TestBrowser/1.0', $sent);
        self::assertSame($request->getContent(), $this->upstreamCalls[0]['options']['body']);
    }

    public function testUnreachableUmamiGivesBadGateway(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'down']));
        $proxy = new StatsProxyController($client, new ArrayAdapter(), 'https://umami.example', 'site');

        self::assertSame(502, $proxy->collect($this->collectRequest('site'))->getStatusCode());
        self::assertSame(502, $proxy->script()->getStatusCode());
    }

    private function proxy(string $url, string $websiteId): StatsProxyController
    {
        $client = new MockHttpClient(function (string $method, string $requestUrl, array $options): MockResponse {
            $this->upstreamCalls[] = ['method' => $method, 'url' => $requestUrl, 'options' => $options];

            return str_ends_with($requestUrl, '/script.js')
                ? new MockResponse('fetch(`${host}/api/send`,{method:"POST"})')
                : new MockResponse('{"cache":"abc"}', ['http_code' => 200]);
        });

        return new StatsProxyController($client, new ArrayAdapter(), $url, $websiteId);
    }

    private function collectRequest(string $websiteId): Request
    {
        return Request::create('/_s/c', 'POST', content: json_encode(['type' => 'event', 'payload' => ['website' => $websiteId, 'name' => 'tour-create']], \JSON_THROW_ON_ERROR));
    }
}
