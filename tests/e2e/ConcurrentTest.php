<?php

namespace UMA\RedisSessions\Tests\E2E;

use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class ConcurrentTest extends EndToEndTestCase
{
    const CONCURRENCY_LEVEL = 20;
    const REQUESTS_PER_TEST = 200;

    /**
     * This test sends a barrage of anonymous requests.
     *
     * After this, Redis should contain exactly REQUESTS_PER_TEST entries.
     */
    public function testAnonymousRequests()
    {
        $this->massSend(
            new Request('GET', '/visit-counter.php')
        );

        $this->assertSame(self::REQUESTS_PER_TEST, $this->redis->dbSize());
    }

    /**
     * This test first sends an anonymous request to trigger the creation of
     * a new session, then a barrage of authenticated requests, then a last request.
     *
     * After this, Redis should contain exactly one entry, and it's number of
     * visits should be exactly REQUESTS_PER_TEST + 2
     *
     * This is the crucial test that exercises session locking. You can temporarily
     * comment the spinlock in the RedisSessionHandler class to induce concurrency
     * bugs and see the test fail.
     */
    public function testAuthenticatedRequests()
    {
        $firstResponse = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $this->massSend(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse))
        );

        $lastResponse = $this->http->send(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse))
        );

        $this->assertSame((string)(self::REQUESTS_PER_TEST + 2), (string) $lastResponse->getBody());

        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * This test sends a barrage of requests attempting a session fixation attack.
     *
     * After this, the cache should contain exactly REQUESTS_PER_TEST entries.
     * Finding just one entry in Redis would mean that the attack was successful.
     */
    public function testMaliciousRequests()
    {
        $this->massSend(
            new Request('GET', '/visit-counter.php', ['Cookie' => 'PHPSESSID=madeupkey;'])
        );

        $this->assertSame(self::REQUESTS_PER_TEST, $this->redis->dbSize());
    }

    /**
     * A helper that asynchronously sends multiple identical requests to the web server,
     * then waits for all those requests to complete.
     *
     * @param Request $request
     */
    protected function massSend(Request $request)
    {
        $replicator = function (Request $request) {
            for ($i = 0; $i < self::REQUESTS_PER_TEST; ++$i) {
                yield clone $request;
            }
        };

        (new Pool(
            $this->http,
            $replicator($request->withProtocolVersion('1.0')),
            ['concurrency' => self::CONCURRENCY_LEVEL]
        ))->promise()->wait();
    }
}
