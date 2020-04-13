<?php

namespace UMA\RedisSessions\Tests\E2E;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Redis;

abstract class EndToEndTestCase extends TestCase
{
    /**
     * @var Client
     */
    protected $http;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * e2e test cases start creating an HTTP client pointing
     * to the testing web server and flushing the Redis database.
     */
    public function setUp()
    {
        $this->http = new Client(['base_uri' => 'http://testapp']);

        $this->redis = new Redis();
        $this->redis->connect('redis');
        $this->redis->flushAll();
    }

    /**
     * A helper that takes a Guzzle response with a 'Set-Cookie'
     * header and prepares the headers array for a subsequent
     * authenticated request.
     *
     * @param ResponseInterface $response
     *
     * @return array
     *
     * @example ['Cookie' => 'PHPSESSID=urbigl47u82h0r9qke0q8jt23836j4gos5kebb0ed5ukiqepsau0;']
     */
    protected function prepareSessionHeader(ResponseInterface $response)
    {
        return [
            'Cookie' => explode(' ', $response->getHeaderLine('Set-Cookie'))[0]
        ];
    }
}
