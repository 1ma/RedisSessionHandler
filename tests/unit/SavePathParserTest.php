<?php

namespace UMA\RedisSessions\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMA\SavePathParser;

class SavePathParserTest extends TestCase
{
    /**
     * @param string $savePath
     * @param array  $expectedParsing
     *
     * @dataProvider savePathsProvider
     */
    public function testParsing($savePath, array $expectedParsing)
    {
        self::assertSame($expectedParsing, SavePathParser::parse($savePath));
    }

    public function savePathsProvider()
    {
        return [
            'compact host' => [
                'localhost',
                ['localhost', SavePathParser::DEFAULT_PORT, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, SavePathParser::DEFAULT_DATABASE]
            ],
            'compact IP' => [
                '1.2.3.4',
                ['1.2.3.4', SavePathParser::DEFAULT_PORT, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, SavePathParser::DEFAULT_DATABASE]
            ],
            'Unix path URI' => [
                'unix:///var/run/redis/redis.sock?database=3',
                ['/var/run/redis/redis.sock', SavePathParser::DEFAULT_PORT, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, 3]
            ],
            'Unix path URI without prefix' => [
                '/var/run/redis/redis.sock?database=3',
                ['/var/run/redis/redis.sock', SavePathParser::DEFAULT_PORT, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, 3]
            ],
            'with port' => [
                'localhost:1234',
                ['localhost', 1234, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, SavePathParser::DEFAULT_DATABASE]
            ],
            'with protocol' => [
                'tcp://localhost:1234',
                ['localhost', 1234, SavePathParser::DEFAULT_TIMEOUT, SavePathParser::DEFAULT_PREFIX, SavePathParser::DEFAULT_AUTH, SavePathParser::DEFAULT_DATABASE]
            ],
            'IP with some options' => [
                'tcp://1.2.3.4:5678?prefix=APP_SESSIONS:&database=3',
                ['1.2.3.4', 5678, SavePathParser::DEFAULT_TIMEOUT, 'APP_SESSIONS:', SavePathParser::DEFAULT_AUTH, 3]
            ],
            'all options' => [
                'tcp://localhost:1234?prefix=APP_SESSIONS:&auth=secret&timeout=1.4&database=3',
                ['localhost', 1234, 1.4, 'APP_SESSIONS:', 'secret', 3]
            ],
        ];
    }
}
