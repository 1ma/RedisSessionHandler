# RedisSessionHandler

An alternative Redis session handler featuring session locking and strict mode.


## Installation

RedisSessionHandler requires PHP >=5.5 with the phpredis extension enabled and a reachable Redis instance running >=2.6. Add [`uma/redis-session-handler`](https://packagist.org/packages/uma/redis-session-handler) to the `composer.json` file:

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
the Redis server, just like the vanilla phpredis session handler.

```ini
session.save_path = "tcp://1.2.3.4:6379"
```


## Motivation

The Redis session handler bundled with [phpredis](https://github.com/phpredis/phpredis) has had a couple of rather serious
bugs for years, namely the [lack of per-session locking](https://github.com/phpredis/phpredis/issues/37) and the [impossibility to protect against session fixation attacks](https://github.com/phpredis/phpredis/issues/37).

This package provides a session handler built on top of the Redis extension that is not affected by these issues.


### Session Locking explained

In the context of PHP, "session locking" means that when multiple requests with the same session ID hit the server roughly
at the same time, only one gets to run while the others get stuck waiting inside `session_start()`. Only when that first request
calls [`session_write_close()`](http://php.net/manual/en/function.session-write-close.php), one of the others can move on.

When a session handler does not implement session locking concurrency bugs might start to surface under
heavy traffic. I'll demonstrate the problem using the default phpredis handler and this simple script:

```
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
1ma@werkbox:~$ http localhost/visit-counter.php
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

1ma@werkbox:~$ hey -n 200 -H "Cookie: PHPSESSID=9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21;" http://localhost/visit-counter.php
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
database, we see that the counter is way off. Instead of 201 visits we see a random number that is way lower than that:

```
127.0.0.1:6379> KEYS *
1) "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"

127.0.0.1:6379> GET PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21
"visits|i:134;"
```

Looking at Redis' `MONITOR` output we can see that under heavy load, Redis often executes two or more `GET` commands
one after the other, thus returning the same number of visits to two or more different requests. When that happens, all
those unlucky requests pass the same number of visits back to Redis, so some of them are ultimately lost. For instance, in this excerpt
you can see how the 130th request is not accounted for.

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
session IDs, a malicious attacker might be able to trick other users into using an ID already known to him, then let them log in and hijack their session.

Starting from PHP 5.5.2, there's an INI directive called [`session.use_strict_mode`](http://php.net/manual/en/session.configuration.php#ini.session.use-strict-mode) to protect
PHP applications against such attacks. When "strict mode" is enabled and a random session ID is received, PHP should ignore it and generate a new
one, just as if it was not there at all. Unfortunately the phpredis handler ignores that directive and always trust whatever session ID is received from
the HTTP request.

```bash
1ma@werkbox:~$ http -v http://localhost/visit-counter.php Cookie:PHPSESSID=madeupkey
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

1ma@werkbox:~$ redis-cli

127.0.0.1:6379> keys *
1) "PHPREDIS_SESSION:madeupkey"

127.0.0.1:6379> GET PHPREDIS_SESSION:madeupkey
"visits|i:1;"
```

RedisSessionHandler also ignores the `session.use_strict_mode` directive but to do the opposite, i.e. make the above behaviour impossible.


## Running the tests

To run the tests you need Docker >=1.10 and docker-compose >=1.8. The next steps are spinning up the testing
environment, downloading the dev dependencies and launching the test sutie.

```bash
1ma@werkbox:~/RedisSessionHandler$ docker-compose -f tests/docker-compose.yml up -d
Creating network "tests_default" with the default driver
Creating tests_fpm_1
Creating tests_redis_1
Creating tests_nginx_1
Creating tests_testrunner_1

1ma@werkbox:~/RedisSessionHandler$ docker exec -it tests_testrunner_1 composer install
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
Nothing to install or update
Generating autoload files

1ma@werkbox:~/RedisSessionHandler$ docker exec -it tests_testrunner_1 php vendor/bin/phpunit
PHPUnit 4.8.31 by Sebastian Bergmann and contributors.

.......

Time: 22.74 seconds, Memory: 8.00MB

OK (7 tests, 27 assertions)
```
