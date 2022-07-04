<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Service;

use Ekyna\Bundle\DigitalOceanBundle\Exception\ApiException;
use Ekyna\Bundle\DigitalOceanBundle\Exception\SpaceNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

use function json_decode;

/**
 * Class Api
 * @package Ekyna\Bundle\DigitalOceanBundle\Service
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class Api
{
    private const ENDPOINT = 'https://api.digitalocean.com/v2/';

    private ?ClientInterface $client = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly string   $token
    ) {
    }

    /**
     * Purges the space CDN cache, optionally for the given files list.
     */
    public function purgeSpace(string $name, array $files = []): void
    {
        $id = $this->getSpaceId($name);

        try {
            $response = $this->getClient()->request('DELETE', "cdn/endpoints/$id/cache", [
                'json' => [
                    'files' => empty($files) ? ['*'] : $files,
                ],
            ]);
        } catch (GuzzleException) {
            throw new ApiException("Failed to purge space '$name' cache.");
        }

        if ($response->getStatusCode() !== 204) {
            throw new ApiException("Failed to purge space '$name' cache.");
        }
    }

    /**
     * Returns the space CDN id.
     */
    public function getSpaceId(string $name): string
    {
        $space = $this->registry->get($name);

        $endpoint = sprintf('%s.%s.cdn.digitaloceanspaces.com', $space['name'], $space['region']);

        try {
            $response = $this->getClient()->request('GET', 'cdn/endpoints');
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException) {
            throw new ApiException("Failed to fetch space '$name' endpoints.");
        }

        foreach ($data['endpoints'] as $datum) {
            if ($datum['endpoint'] === $endpoint) {
                return $datum['id'];
            }
        }

        throw new SpaceNotFoundException($name);
    }

    /**
     * Returns the http client.
     *
     * @return ClientInterface
     */
    private function getClient(): ClientInterface
    {
        if ($this->client) {
            return $this->client;
        }

        return $this->client = new Client([
            'base_uri' => self::ENDPOINT,
            'headers'  => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);
    }
}
