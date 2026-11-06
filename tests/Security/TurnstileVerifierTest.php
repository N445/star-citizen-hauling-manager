<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Turnstile\TurnstileVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TurnstileVerifierTest extends TestCase
{
    private int $calls = 0;

    public function testDisabledWithoutKeysAcceptsEverything(): void
    {
        $verifier = $this->verifier('', '', '{"success":false}');

        self::assertFalse($verifier->isEnabled());
        self::assertTrue($verifier->verify(null));
        self::assertSame(0, $this->calls);
    }

    public function testMissingTokenIsRefusedWithoutCallingCloudflare(): void
    {
        $verifier = $this->verifier('site', 'secret', '{"success":true}');

        self::assertFalse($verifier->verify(null));
        self::assertFalse($verifier->verify(''));
        self::assertSame(0, $this->calls);
    }

    public function testValidTokenIsAccepted(): void
    {
        self::assertTrue($this->verifier('site', 'secret', '{"success":true}')->verify('token'));
        self::assertSame(1, $this->calls);
    }

    public function testRejectedTokenIsRefused(): void
    {
        self::assertFalse($this->verifier('site', 'secret', '{"success":false,"error-codes":["invalid-input-response"]}')->verify('token'));
    }

    public function testUnreachableCloudflareFailsClosed(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'down']));

        self::assertFalse((new TurnstileVerifier($client, 'site', 'secret'))->verify('token'));
    }

    private function verifier(string $siteKey, string $secretKey, string $body): TurnstileVerifier
    {
        $client = new MockHttpClient(function () use ($body): MockResponse {
            ++$this->calls;

            return new MockResponse($body, ['response_headers' => ['content-type: application/json']]);
        });

        return new TurnstileVerifier($client, $siteKey, $secretKey);
    }
}
