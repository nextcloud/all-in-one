<?php

namespace AIO\Docker;

use AIO\ContainerDefinitionFetcher;
use AIO\Data\ConfigurationManager;
use GuzzleHttp\Client;

class DockerHubManager
{
    private Client $guzzleClient;

    public function __construct()
    {
        $this->guzzleClient = new Client();
    }

    public function GetLatestDigestOfTag(string $name, string $tag) : ?string {
        $cacheKey = 'dockerhub-manifest-' . $name . $tag;

        $cachedVersion = apcu_fetch($cacheKey);
        if($cachedVersion !== false && is_string($cachedVersion)) {
            return $cachedVersion;
        }

        // If one of the links below should ever become outdated, we can still upgrade the mastercontainer via the webinterface manually by opening '/api/docker/getwatchtower'

        try {
            $request = $this->guzzleClient->request(
                'GET',
                'https://hub.docker.com/v2/repositories/' . $name . '/tags?page_size=128'
            );
            $body = $request->getBody()->getContents();
            $decodedBody = json_decode($body, true);

            if (isset($decodedBody['results'])) {
                $arch = php_uname('m') === 'x86_64' ? 'amd64' : 'arm64';
                foreach($decodedBody['results'] as $values) {
                    if (isset($values['name'])
                    && $values['name'] === $tag
                    && isset($values['images'])
                    && is_array($values['images'])) {
                        foreach ($values['images'] as $images) {
                            if (isset($images['architecture'])
                            && $images['architecture'] === $arch
                            && isset($images['digest'])
                            && is_string($images['digest'])) {
                                $latestVersion = $images['digest'];
                                apcu_add($cacheKey, $latestVersion, 600);
                                return $latestVersion;
                            }
                        }
                    }
                }
            }

            error_log('Could not get digest of container ' . $name . ':' . $tag);
            return null;
        } catch (\Exception $e) {
            error_log('Could not get digest of container ' . $name . ':' . $tag . ' ' . $e->getMessage());
            return null;
        }
    }
}