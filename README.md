# RedisSessionHandler

[![Build Status](https://scrutinizer-ci.com/g/1ma/RedisSessionHandler/badges/build.png?b=master)](https://scrutinizer-ci.com/g/1ma/RedisSessionHandler/build-status/master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/1ma/RedisSessionHandler/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/1ma/RedisSessionHandler/?branch=master) [![Latest Stable Version](https://poser.pugx.org/uma/redis-session-handler/v/stable)](https://packagist.org/packages/uma/redis-session-handler) [![Monthly Downloads](https://poser.pugx.org/uma/redis-session-handler/d/monthly)](https://packagist.org/packages/uma/redis-session-handler)

An alternative Redis session handler featuring session locking and session fixation protection.


## News

* phpredis v4.1.0 (released on 2018-07-10) added support for session locking, but it is disabled by default. To enable
  it you must set the new `redis.session.locking_enabled` INI directive to `true`. This version is the first to pass
  the test in `ConcurrentTest` that stresses the locking mechanism.


## Installation

RedisSessionHandler requires PHP >=5.6 with the phpredis extension enabled and a Redis >=2.6 endpoint. Add [`uma/redis-session-handler`](https://packagist.org/packages/uma/redis-session-handler) to the `composer.json` file:

    $ composer require uma/redis-session-handler

Overwrite the default session handler with `UMA\RedisSessionHandler` before your application calls
any `session_` function. If you are using a framework and unsure when or where that happens, a
good rule of thumb is "as early as possible". A safe bet might be the frontend controller in the
public directory of the project or an equivalent initialization script.

```php
// top of my-project/web/app.php

require_once __DIR__ . '/../vendor/autoload.php';

session_set_save_handler(new \UMA\RedisSessionHandler(), true);
```

Note that calling `session_set_save_handler` overwrites any value you might have set in the `session.save_handler` option
of the php.ini file, so you don't need to change that. However, RedisSessionHandler still uses `session.save_path` to find
the Redis server, just like the vanilla phpredis session handler:

```ini
; examples
session.save_path = "localhost"
session.save_path = "localhost?timeout=2.5"
session.save_path = "tcp://1.2.3.4:5678?prefix=APP_SESSIONS:&database=2"
```

Available query params:

* `timeout` (float), default `0.0`, which means unlimited timeout
* `prefix` (string), default `'PHPREDIS_SESSION:'`
* `auth` (string), default `null`
* `database` (int), default `0`

Currently only a single host definition is supported.


## Known Caveats

### Using RedisSessionHandler with the `max_execution_time` directive set to `0` is not recommended

Whenever it can, the handler uses the `max_execution_time` directive as a hard timeout for the session lock. This is a
last resort mechanism to release the session lock even if the PHP process crashes and the handler fails to do it itself.

When `max_execution_time` is set to `0` (meaning there is no maximum execution time) this kind of hard timeout cannot be used, as the lock
must be kept for as long as it takes to run the script, which is an unknown amount of time. This means that if for some unexpected reason
the PHP process crashes and the handler fails to release the lock there would be no safety net and you'd end up with a dangling lock
that you'd have to detect and purge by other means.

So when using RedisSessionHandler it is advised _not_ to disable `max_execution_time`.


### RedisSessionHandler does not support `session.use_trans_sid=1` nor `session.use_cookies=0`

When these directives are set this way PHP switches from using cookies to passing the session ID around as a query param.

RedisSessionHandler cannot work in this mode. _This is by design_.


### RedisSessionHandler ignores the `session.use_strict_mode` directive

Because running PHP with strict mode disabled (which is the default!) does not make any sense whatsoever. RedisSessionHandler
only works in strict mode. The _Session fixation_ section of this README explains what that means.


## Motivation

The Redis session handler bundled with [phpredis](https://github.com/phpredis/phpredis) has had a couple of rather serious
bugs for years, namely the [lack of per-session locking](https://github.com/phpredis/phpredis/issues/37) and the [impossibility to protect against session fixation attacks](https://github.com/phpredis/phpredis/issues/1033).

This package provides a compatible session handler built on top of the Redis extension that is not affected by these issues.


### Session Locking explained

In the context of PHP, "session locking" means that when multiple requests with the same session ID hit the server roughly
at the same time, only one gets to run while the others get stuck waiting inside `session_start()`. Only when that first request
finishes or explicitly runs [`session_write_close()`](http://php.net/manual/en/function.session-write-close.php), one of the others can move on.

When a session handler does not implement session locking concurrency bugs might start to surface under
heavy traffic. I'll demonstrate the problem using the default phpredis handler and this simple script:

```php
<?php

// a script that returns the total number of
// requests made during a given session's lifecycle.

session_start();

if (!isset($_SESSION['visits'])) {
  $_SESSION['visits'] = 0;
}

$_SESSION['visits']++;

echo $_SESSION['visits'];
```

First, we send a single request that will setup a new session. Then we use the session ID returned in
the `Set-Cookie` header to send a burst of 200 concurrent, authenticated requests.

```bash
$ http localhost/visit-counter.php
HTTP/1.1 200 OK
Cache-Control: no-store, no-cache, must-revalidate
Connection: keep-alive
Content-Type: text/html; charset=UTF-8
Date: Mon, 23 Jan 2017 12:30:17 GMT
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Pragma: no-cache
Server: nginx/1.11.8
Set-Cookie: PHPSESSID=9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21; path=/; HttpOnly
Transfer-Encoding: chunked

1

$ hey -n 200 -H "Cookie: PHPSESSID=9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21;" http://localhost/visit-counter.php
All requests done.

Summary:
  Total:	0.4033 secs
  Slowest:	0.1737 secs
  Fastest:	0.0086 secs
  Average:	0.0805 secs
  Requests/sec:	495.8509

Status code distribution:
  [200]	200 responses
```

Everything looks fine from the outside, we got the expected two hundred OK responses, but if we peek inside the Redis
database we see that the counter is way off. Instead of 201 visits we see a random number that is way lower than that:

```
127.0.0.1:6379> KEYS *
1) "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"

127.0.0.1:6379> GET PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21
"visits|i:134;"
```

Looking at Redis' `MONITOR` output we can see that under heavy load, Redis often executes two or more `GET` commands
one after the other, thus returning the same number of visits to two or more different requests. When that happens, all
those unlucky requests pass the same number of visits back to Redis, so some of them are ultimately lost. For instance, in this excerpt
of the log you can see how the 130th request is not accounted for.

```
1485174643.241711 [0 172.21.0.2:49780] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.241891 [0 172.21.0.2:49782] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.242444 [0 172.21.0.2:49782] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:129;"
1485174643.242878 [0 172.21.0.2:49780] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:129;"
1485174643.244780 [0 172.21.0.2:49784] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.245385 [0 172.21.0.2:49784] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:130;"
```

RedisSessionHandler solves this problem with a "lock" entry for every session that only one thread of execution can create at a time.


### Session fixation explained

[Session fixation](https://www.owasp.org/index.php/Session_fixation) is the ability to choose your own session ID as an HTTP client. When clients are allowed to choose their
session IDs, a malicious attacker might be able to trick other clients into using an ID already known to him, then wait for them log in and hijack their session.

Starting from PHP 5.5.2, there's an INI directive called [`session.use_strict_mode`](http://php.net/manual/en/session.configuration.php#ini.session.use-strict-mode) to protect
PHP applications against such attacks. When "strict mode" is enabled and a unknown session ID is received, PHP should ignore it and generate a new
one, just as if it was not received at all. Unfortunately the phpredis handler ignores that directive and always trust whatever session ID is received from
the HTTP request.

```bash
$ http -v http://localhost/visit-counter.php Cookie:PHPSESSID=madeupkey
GET / HTTP/1.1
Accept: */*
Accept-Encoding: gzip, deflate
Connection: keep-alive
Cookie: PHPSESSID=madeupkey     <----
Host: 127.0.0.1
User-Agent: HTTPie/0.9.6

HTTP/1.1 200 OK
Cache-Control: no-store, no-cache, must-revalidate
Connection: close
Content-type: text/html; charset=UTF-8
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Host: 127.0.0.1
Pragma: no-cache

1

$ redis-cli

127.0.0.1:6379> keys *
1) "PHPREDIS_SESSION:madeupkey"

127.0.0.1:6379> GET PHPREDIS_SESSION:madeupkey
"visits|i:1;"
```

Hence RedisSessionHandler only works in strict mode. It only accepts external session IDs
that are already inside the Redis store.


## Testing

### Running the tests

To do that you'll need Docker >=1.10 and docker-compose >=1.8.

In order to run the integration test suite just type `composer test` and it will take care of installing
the dev dependencies, setting up the testing containers and running the tests.

```bash
$ composer test
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
Nothing to install or update
Generating autoload files
> docker-compose -f tests/docker-compose.yml up -d
tests_redis_1 is up-to-date
tests_fpm56_1 is up-to-date
tests_fpm71_1 is up-to-date
tests_fpm70_1 is up-to-date
tests_redis_monitor_1 is up-to-date
tests_nginx_1 is up-to-date
tests_runner_1 is up-to-date
> docker exec -t tests_runner_1 sh -c "TARGET=php56 vendor/bin/phpunit"
stty: standard input
PHPUnit 6.2.3 by Sebastian Bergmann and contributors.

................           16 / 16 (100%)

Time: 1.39 seconds, Memory: 4.00MB

OK (16 tests, 54 assertions)
> docker exec -t tests_runner_1 sh -c "TARGET=php70 vendor/bin/phpunit"
stty: standard input
PHPUnit 6.2.3 by Sebastian Bergmann and contributors.

................           16 / 16 (100%)

Time: 1.29 seconds, Memory: 4.00MB

OK (16 tests, 54 assertions)
> docker exec -t tests_runner_1 sh -c "TARGET=php71 vendor/bin/phpunit"
stty: standard input
PHPUnit 6.2.3 by Sebastian Bergmann and contributors.

................           16 / 16 (100%)

Time: 1.08 seconds, Memory: 4.00MB

OK (16 tests, 54 assertions)
```


### Running the tests against the native phpredis handler

You can easily run the same test suite against the native phpredis handler.

To do so, comment out the line in `tests/webroot/visit-counter.php` where RedisSessionHandler is
enabled and the FPM container will automatically choose the phpredis save handler (version 3.1.2 at the time of writing).

```php
// session_set_save_handler(new \UMA\RedisSessionHandler(), true);
```

```bash
$ composer test
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
Nothing to install or update
Generating autoload files
> docker-compose -f tests/docker-compose.yml up -d
tests_redis_1 is up-to-date
tests_fpm56_1 is up-to-date
tests_fpm71_1 is up-to-date
tests_fpm70_1 is up-to-date
tests_redis_monitor_1 is up-to-date
tests_nginx_1 is up-to-date
tests_runner_1 is up-to-date
> docker exec -t tests_runner_1 sh -c "TARGET=php56 vendor/bin/phpunit"
stty: standard input
PHPUnit 6.2.3 by Sebastian Bergmann and contributors.

...FFF..FF......           16 / 16 (100%)

Time: 1.15 seconds, Memory: 4.00MB

There were 5 failures:

~~snip~~
```


### Manual testing

The `docker-compose.yml` file is configured to expose a random TCP port linked to the nginx container port 80. After running
`composer env-up` or `composer test` you can see which one was assigned with `docker ps`. With that knowledge you can
poke the testing webserver directly from your local machine using either a regular browser, cURL, wrk or similar tools.

Depending on your Docker setup you might need to replace `localhost` with the IP of the virtual machine that actually runs the Docker daemon:

```bash
$ curl -i localhost:32768/visit-counter.php?with_custom_cookie_params
HTTP/1.1 200 OK
Server: nginx/1.13.1
Date: Sat, 15 Jul 2017 09:40:25 GMT
Content-Type: text/html; charset=UTF-8
Transfer-Encoding: chunked
Connection: keep-alive
Set-Cookie: PHPSESSID=tqm3akv0t5gdf25lf1gcspn479aa989jp0mltc3nuun2b47c7g10; expires=Sun, 16-Jul-2017 09:40:25 GMT; Max-Age=86400; path=/; secure; HttpOnly
Expires: Thu, 19 Nov 1981 08:52:00 GMT
Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0
Pragma: no-cache

1
```
