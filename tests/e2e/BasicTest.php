<?php

namespace UMA\RedisSessions\Tests\E2E;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use UMA\SavePathParser;

class BasicTest extends EndToEndTestCase
{
    /**
     * This test sends an anonymous request to a PHP
     * script that will create a session.
     *
     * After this, the response should have a 'Set-Cookie' header
     * with the new session ID, its body should be '1', and Redis
     * should have exactly one entry.
     */
    public function testAnonymousRequest()
    {
        $response = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $this->assertSame('1', (string) $response->getBody());

        $this->assertCreatedNewSession($response);

        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * This test sends two anonymous, unrelated requests to a PHP
     * script that will create a session for each one of them.
     *
     * After this, each response should have a Set-Cookie header
     * with its new session ID (each one should be different), and
     * both their bodies must be '1'. Redis should have exactly
     * two entries.
     */
    public function testUnrelatedAnonymousRequests()
    {
        $firstResponse = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $secondResponse = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $this->assertSame('1', (string) $firstResponse->getBody());
        $this->assertSame('1', (string) $secondResponse->getBody());

        $this->assertCreatedNewSession($firstResponse);
        $this->assertCreatedNewSession($secondResponse);
        $this->assertNotSame($firstResponse->getHeaderLine('Set-Cookie'), $secondResponse->getHeaderLine('Set-Cookie'));

        $this->assertSame(2, $this->redis->dbSize());
    }

    /**
     * This test sends an anonymous request, then a second one
     * authenticated with the cookie received in the first.
     *
     * After this, the first response should have a 'Set-Cookie' header
     * with its new session ID, and its body should be '1'.The second
     * response should NOT have a 'Set-Cookie' header and its body
     * should be '2'. Redis should have exactly one entry.
     */
    public function testRelatedRequests()
    {
        $firstResponse = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $secondResponse = $this->http->send(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse))
        );

        $this->assertSame('1', (string) $firstResponse->getBody());
        $this->assertSame('2', (string) $secondResponse->getBody());

        $this->assertCreatedNewSession($firstResponse);
        $this->assertFalse($secondResponse->hasHeader('Set-Cookie'));

        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * This test sends a malicious request attempting a session
     * fixation attack.
     *
     * After this, the response should have a 'Set-Cookie' header with
     * a newly generated ID and its body should be '1'. Redis should
     * have exactly one entry, and its key should not be 'madeupkey'.
     *
     * Additionally, the Set-Cookie header MUST NOT have an 'expires' tag. This
     * was a bug where a forcefully regenerated session ID used to be stamped
     * with an instant expiration time.
     */
    public function testMaliciousRequest()
    {
        $response = $this->http->send(
            new Request('GET', '/visit-counter.php', ['Cookie' => 'PHPSESSID=madeupkey;'])
        );

        $this->assertSame('1', (string) $response->getBody());

        $this->assertCreatedNewSession($response);
        $this->assertNotRegExp('/expires=/i', $response->getHeaderLine('Set-Cookie'));

        $this->assertSame(1, $this->redis->dbSize());
        $this->assertFalse($this->redis->get(SavePathParser::DEFAULT_PREFIX.'madeupkey'));
    }

    /**
     * This test sends a malicious request attempting a session
     * fixation attack but uses custom cookie params.
     *
     * After this, the response should have a 'Set-Cookie' header with
     * a newly generated ID and its body should be '1'. The cookie should
     * have custom parameters as per visit-counter.php and Redis should
     * have exactly one entry, and its key should not be 'madeupkey'.
     */
    public function testMaliciousRequestWithCustomCookieParams()
    {
        $response = $this->http->send(
            new Request('GET', '/visit-counter.php?with_custom_cookie_params', ['Cookie' => 'PHPSESSID=madeupkey;'])
        );

        $this->assertSame('1', (string) $response->getBody());

        $this->assertCustomCookieParams($response);

        $this->assertSame(1, $this->redis->dbSize());
        $this->assertFalse($this->redis->get(SavePathParser::DEFAULT_PREFIX.'madeupkey'));
    }


    /**
     * This test checks that the server behaves correctly when a previously valid
     * session ID is removed from Redis in between requests.
     *
     * @see https://github.com/1ma/RedisSessionHandler/issues/3
     */
    public function testFlushedDatabase()
    {
        $firstResponse = $this->http->send(
            new Request('GET', '/visit-counter.php')
        );

        $this->assertSame('1', (string) $firstResponse->getBody());
        $this->assertCreatedNewSession($firstResponse);

        $this->redis->flushAll();

        $this->assertSame(0, $this->redis->dbSize());

        $secondResponse = $this->http->send(
            new Request('GET', '/visit-counter.php', $this->prepareSessionHeader($firstResponse))
        );

        $this->assertSame('1', (string) $secondResponse->getBody());
        $this->assertCreatedNewSession($secondResponse);

        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * This test checks that the server behaves correctly when a script is run that has no time limit
     */
    public function testUnlimitedExecutionTime()
    {
        $response = $this->http->send(
            new Request('GET', '/visit-counter.php?with_no_time_limit')
        );

        $this->assertSame('1', (string) $response->getBody());
        $this->assertCreatedNewSession($response);
        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * Asserts whether a received request triggered the creation of a new session.
     * It does so by searching for and inspecting the 'Set-Cookie' header.
     *
     * @param ResponseInterface $response
     */
    protected function assertCreatedNewSession(ResponseInterface $response)
    {
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * Asserts whether a received request triggered the creation of a new session with example custom parameters.
     * It does so by searching for and inspecting the 'Set-Cookie' header.
     * 
     * Expected result:
     * set-cookie:PHPSESSID=0691f472784601a324eadf78c05cf4c1; expires=Tue, 11-Jul-2017 14:58:34 GMT; Max-Age=86400; path=/; secure; HttpOnly
     *
     * @param ResponseInterface $response
     */
    protected function assertCustomCookieParams(ResponseInterface $response)
    {
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $response->getHeaderLine('Set-Cookie'));
        $this->assertRegExp('/expires=/i', $response->getHeaderLine('Set-Cookie'));
        $this->assertRegExp('/path=/i', $response->getHeaderLine('Set-Cookie'));
        $this->assertRegExp('/secure/i', $response->getHeaderLine('Set-Cookie'));
        $this->assertRegExp('/HttpOnly/i', $response->getHeaderLine('Set-Cookie'));
    }
}



