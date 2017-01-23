<?php

namespace UMA\RedisSessions\Tests;

use GuzzleHttp\Psr7\Request;

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
            new Request('GET', '/visit-counter.php', [], null, '1.0')
        );

        $this->assertSame('1', (string) $response->getBody());
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $response->getHeaderLine('Set-Cookie'));

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
            new Request('GET', '/visit-counter.php', [], null, '1.0')
        );

        $secondResponse = $this->http->send(
            new Request('GET', '/visit-counter.php', [], null, '1.0')
        );

        $this->assertSame('1', (string) $firstResponse->getBody());
        $this->assertSame('1', (string) $secondResponse->getBody());
        $this->assertTrue($firstResponse->hasHeader('Set-Cookie'));
        $this->assertTrue($secondResponse->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $firstResponse->getHeaderLine('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $secondResponse->getHeaderLine('Set-Cookie'));
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
        $this->assertTrue($firstResponse->hasHeader('Set-Cookie'));
        $this->assertFalse($secondResponse->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $firstResponse->getHeaderLine('Set-Cookie'));

        $this->assertSame(1, $this->redis->dbSize());
    }

    /**
     * This test sends a malicious request attempting to pull off
     * a session fixation attack.
     *
     * After this, the response should have a 'Set-Cookie' header with
     * a newly generated ID and its body should be '1'. Redis should
     * have exactly one entry, and its key should not be 'madeupkey'.
     */
    public function testMaliciousRequest()
    {
        $response = $this->http->send(
            new Request('GET', '/visit-counter.php', ['Cookie' => 'PHPSESSID=madeupkey;'])
        );

        $this->assertSame('1', (string) $response->getBody());
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringStartsWith('PHPSESSID=', $response->getHeaderLine('Set-Cookie'));

        $this->assertSame(1, $this->redis->dbSize());
        $this->assertFalse($this->redis->get('madeupkey'));
    }
}
