<?php

namespace UMA\RedisSessions\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class EndToEndTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * e2e test cases start creating a connection against the
     * web server container.
     */
    public function setUp()
    {
        $this->client = new Client(['base_uri' => 'http://nginx']);

        $redis = new \Redis();
        $redis->connect('redis');

        $redis->flushAll();
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
     * @example ['Cookie' => 'PHPSESSID=gf393t479t1s3uv263jklqq8o4;']
     */
    protected function prepareSessionHeader(Response $response)
    {
        return [
            'Cookie' => explode(' ', $response->getHeaderLine('Set-Cookie'))[0],
        ];
    }
}
