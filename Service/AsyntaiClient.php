<?php

declare(strict_types=1);

namespace MauticPlugin\AsyntaiBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Reads leads from the Asyntai API with the account's API key.
 */
class AsyntaiClient
{
    public const VERSION = '1.0.0';

    private ?ClientInterface $client = null;

    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    /**
     * Tests put a fake HTTP client here.
     */
    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * One page of leads, newest first.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException with a message that can be shown to the user
     */
    public function leads(string $apiKey, ?string $since, ?string $before, int $limit = 100): array
    {
        $query = ['limit' => $limit];
        if (null !== $since) {
            $query['since'] = $since;
        }
        if (null !== $before) {
            $query['before'] = $before;
        }

        try {
            $response = $this->client()->request('GET', $this->settings->origin().'/api/v1/leads/', [
                'query'   => $query,
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Asyntai-Mautic/'.self::VERSION,
                ],
                'timeout'         => 30,
                'connect_timeout' => 10,
                'http_errors'     => false,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Asyntai could not be reached: '.$e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);
        if (200 !== $response->getStatusCode() || !is_array($body) || empty($body['success'])) {
            $error = is_array($body) && !empty($body['error']) ? (string) $body['error'] : 'HTTP '.$response->getStatusCode();
            throw new \RuntimeException('Asyntai answered: '.$error);
        }

        return is_array($body['leads'] ?? null) ? array_values(array_filter($body['leads'], 'is_array')) : [];
    }

    private function client(): ClientInterface
    {
        return $this->client ??= new Client();
    }
}
