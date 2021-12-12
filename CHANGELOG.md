### [0.9.9] - 2021-12-12

  * (Hotfix) Unlock the required version of `symfony/polyfill-php70`

### [0.9.8] - 2021-12-12

  * (Feature) Support all recent versions of PHP from 7.3 through 8.1 (contributed by [@wgirhad](https://github.com/wgirhad))

### [0.9.7] - 2020-04-07

  * (Improvement) Implemented support for Unix paths as means to connect to a Redis server.
  * (Bugfix) Ensure RedisSessionHandler::read() always returns a string (contributed by [therosco](https://github.com/therosco)).
  * (Docs) Acknowledged `redis.session.locking_enabled` INI directive from the native extension (starting from v4.1.0).
  * (Improvement) Updated versions of testing containers.

### [0.9.6] - 2018-03-31

  * (Bugfix) Fixed regenerated sessions expiration date (contributed by [@kavacky](https://github.com/kavacky))
  * (Bugfix) Fixed a phpredis 4.0 BC break in the return type of the `exists()` function.
  * (Improvement) PHP 7.2 was released since 0.9.5, and a 7.2 FPM daemon has been added to the test pool.

### [0.9.5] - 2017-07-13

  * (Bugfix) Made the handler acknowledge custom session cookie parameters set with `session_set_cookie_params` (contributed by [@scottlucas](https://github.com/scottlucas))
  * (Improvement) Expanded integration tests to cover all supported major versions of PHP (5.6, 7.0 and 7.1).

### [0.9.4] - 2017-07-10

  * (Bugfix) Prevented the handler from hanging when the `max_execution_time` directive is set to `0` (contributed by [@scottlucas](https://github.com/scottlucas))

### [0.9.3] - 2017-04-28

  * (Bugfix) Made the handler compatible with PHP +7.1.2 by avoiding the call to `session_regenerate_id` inside the handler.

### [0.9.2] - 2017-02-20

  * (Improvement) Set up continuous integration based on Scrutinizer.
  * (Feature) Added support for `timeout`, `prefix`, `auth` and `database` query params on the `session.save_path` directive, as in the native handler.

### [0.9.1] - 2017-02-05

  * (Improvement) Introduced exponential backoff between session locking attempts.
  * (Improvement) A session ID is now never locked before checking that it doesn't need to be regenerated.

### [0.9.0] - 2017-02-05

  * Initial pre-release

[0.9.7]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.8...v0.9.9
[0.9.7]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.7...v0.9.8
[0.9.7]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.6...v0.9.7
[0.9.6]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.5...v0.9.6
[0.9.5]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.4...v0.9.5
[0.9.4]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.3...v0.9.4
[0.9.3]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.2...v0.9.3
[0.9.2]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/1ma/RedisSessionHandler/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/1ma/RedisSessionHandler/tree/b6b149a3d5322e49a3c4c933ed8154ad3da30570
