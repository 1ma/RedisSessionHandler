<?php

namespace UMA\RedisSessions\Tests\E2E;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

abstract class EndToEndTestCase extends TestCase
{
    /**
     * Hostname where to send the HTTP requests
     * when $_SERVER['TARGET'] is not available.
     */
    const DEFAULT_TARGET = 'php56';

    /**
     * @var Client
     */
    protected $http;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * e2e test cases start creating an HTTP client pointing
     * to the testing web server and flushing the Redis database.
     */
    public function setUp(): void
    {
        $target = isset($_SERVER['TARGET']) ?
            $_SERVER['TARGET'] : self::DEFAULT_TARGET;

        $this->http = new Client(['base_uri' => "http://$target"]);

        $this->redis = new \Redis();
        $this->redis->connect('redis');
        $this->redis->flushAll();
    }

    /**
     * A helper that takes a Guzzle response with a 'Set-Cookie'
     * header and prepares the headers array for a subsequent
     * authenticated request.
     *
     * @param Response $response
     *
     * @return array
     *
     * @example ['Cookie' => 'PHPSESSID=urbigl47u82h0r9qke0q8jt23836j4gos5kebb0ed5ukiqepsau0;']
     */
    protected function prepareSessionHeader(Response $response)
    {
        return [
            'Cookie' => explode(' ', $response->getHeaderLine('Set-Cookie'))[0],
        ];
    }
}
