<?php

namespace UMA;

/**
 * @author Marcel Hernandez
 */
class SavePathParser
{
    /**
     * Redis port to use when it is not specified in the save path
     */
    const DEFAULT_PORT = 6379;

    /**
     * Connection timeout to use when it is not specified in the save path
     */
    const DEFAULT_TIMEOUT = 0.0;

    /**
     * Session prefix to use when it is not specified in the save path
     */
    const DEFAULT_PREFIX = 'PHPREDIS_SESSION:';

    /**
     * Authentication string to use when it is not specified in the save path
     */
    const DEFAULT_AUTH = null;

    /**
     * Redis database to use when it is not specified in the save path
     */
    const DEFAULT_DATABASE = 0;

    const DEFAULT_OPTIONS = [
        'timeout' => self::DEFAULT_TIMEOUT,
        'prefix' => self::DEFAULT_PREFIX,
        'auth' => self::DEFAULT_AUTH,
        'database' => self::DEFAULT_DATABASE
    ];

    /**
     * @param string $path The session.save_path parameter from php.ini
     *
     * @return array [host, port, connection timeout, session prefix, auth, database]
     *
     * @example 'localhost' => ['localhost', 6379, 0.0, 'PHPREDIS_SESSION:', null, 0]
     * @example 'tcp://1.2.3.4:5678?auth=secret&database=3' => ['1.2.3.4', 5678, 0.0, 'PHPREDIS_SESSION:', 'secret', 3]
     */
    public static function parse($path)
    {
        $parsed_path = parse_url($path);

        $host = isset($parsed_path['host']) ?
            $parsed_path['host'] : $parsed_path['path'];

        $port = isset($parsed_path['port']) ?
            $parsed_path['port'] : static::DEFAULT_PORT;

        $opts = [];

        if (isset($parsed_path['query'])) {
            parse_str($parsed_path['query'], $opts);
        }

        $opts = array_merge(static::DEFAULT_OPTIONS, $opts);

        return [
            $host, $port, (float) $opts['timeout'], $opts['prefix'], $opts['auth'], (int) $opts['database']
        ];
    }
}
