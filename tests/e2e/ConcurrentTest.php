<?php

namespace UMA\RedisSessions\Tests;

use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class ConcurrentTest extends EndToEndTestCase
{
    const CONCURRENCY_LEVEL = 200;
    const REQUESTS_PER_TEST = 2000;

    /**
     * This test sends a barrage of anonymous requests.
     *
     * After this, the cache should contain exactly REQUESTS_PER_TEST entries.
     */
    public function testAnonymousRequests()
    {
        $this->massSend(
            new Request('GET', '/visit-counter.php', [], null, '1.0')
        );
    }

    /**
     * This test first sends an anonymous request to trigger the creation of
     * a new session, then a barrage of authenticated requests, then a last request.
     *
     * After this, the cache should contain exactly one entry, and it's number of
     * visits should be exactly REQUESTS_PER_TEST + 2
     *
     * This is the crucial test that exercises session locking. You can temporarily
     * comment the spinlock in the RedisSessionHandler class to induce a concurrency bug.
     */
    public function testAuthenticatedRequests()
    {
        $firstResponse = $this->client->send(
            new Request('GET', '/visit-counter.php', [], null, '1.0')
        );

        $this->massSend(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse), null, '1.0')
        );

        $lastResponse = $this->client->send(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse), null, '1.0')
        );

        $this->assertSame(strval(self::REQUESTS_PER_TEST + 2), (string) $lastResponse->getBody());
    }

    /**
     * This test sends a barrage of requests attempting a session fixation attack.
     *
     * After this, the cache should contain exactly REQUESTS_PER_TEST entries.
     * Finding just one entry in the cache would mean that the attack was successful.
     */
    public function testMaliciousRequests()
    {
        $this->massSend(
            new Request('GET', '/visit-counter.php', ['Cookie' => 'PHPSESSID=madeupkey;'], null, '1.0')
        );
    }

    /**
     * A helper that asynchronously sends multiple identical requests to the web server.
     * It waits until all requests have been completed.
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
            $this->client,
            $replicator($request),
            ['concurrency' => self::CONCURRENCY_LEVEL]
        ))->promise()->wait();
    }
}
