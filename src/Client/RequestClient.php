<?php
namespace RemoteConfig\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;

class RequestClient
{
    /**
     * @var GuzzleClient
     */
    private $guzzleClient = null;

    /**
     * @var array
     */
    private $config;

    public function __construct(array $config = null)
    {
        $timeout = getenv("CONFIG_TIMEOUT");
        $this->config = [
            "host"      => getenv("CONFIG_HOST"),
            "user"      => getenv("CONFIG_USER"),
            "password"  => getenv("CONFIG_PASSWORD"),
            "timeout"   => $timeout?$timeout:5,
            "env"       => getenv("CONFIG_ENV")
        ];

        if (!is_null($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    public function getConfig(array $services)
    {
        $client = $this->getClient();
        $requestPromises = [];
        foreach ($services as $service) {
            $uri = "/{$service}-default.json";
            if ($this->config['env']) {
                $uri = "/{$this->config['env']}{$uri}";
            }
            $requestPromises[$service] = $client->getAsync($uri);
        }
        $results = Promise\settle($requestPromises)->wait();
        $arrayResult = [];
        foreach ($results as $key => $result) {
            if(isset($result['value'])) {
                $arrayResult[$key] = json_decode($result['value']->getBody()->getContents(),true);
            } elseif ($result['state'] == 'rejected') {
                $arrayResult[$key] = [
                    'error' => 'true',
                    'messaje' => $result['reason']->getMessage()
                ];
            } else {
                $arrayResult[$key] = [];
            }
        }
        return $arrayResult;
    }

    private function getClient()
    {
        if(is_null($this->guzzleClient)) {
            $parameters = [
                'base_uri' => $this->config['host'],
                'timeout'  => $this->config['host']
            ];
            if($this->config["user"] && $this->config["password"]) {
                $parameters['headers'] = ['Authorization' => 'Basic '. base64_encode($this->config["user"].':'.$this->config["password"])];
            }
            $this->guzzleClient = new GuzzleClient($parameters);
        }
        return $this->guzzleClient;
    }
}