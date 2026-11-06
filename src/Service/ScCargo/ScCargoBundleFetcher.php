<?php

declare(strict_types=1);

namespace App\Service\ScCargo;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ScCargoBundleFetcher
{
    private const string SITE = 'https://sc-cargo.space';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function fetch(): string
    {
        $homePage = $this->get('/');

        return $this->get($this->bundlePath($homePage));
    }

    private function bundlePath(string $homePage): string
    {
        if (!preg_match('#src="(/assets/index-[\w-]+\.js)"#', $homePage, $match)) {
            throw new \RuntimeException('Bundle JS introuvable sur la page d\'accueil de sc-cargo.space.');
        }

        return $match[1];
    }

    private function get(string $path): string
    {
        return $this->httpClient->request('GET', self::SITE.$path)->getContent();
    }
}
