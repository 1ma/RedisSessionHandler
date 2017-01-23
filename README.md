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
of the php.ini file, so you don't need to change it. However, RedisSessionHandler still uses `session.save_path` to find
the Redis server, just like the phpredis handle.

```ini
session.save_path = "tcp://1.2.3.4:6379"
```


## Motivation

For years, the C session handler bundled with [phpredis](https://github.com/phpredis/phpredis) has been suffering
from a couple of bugs that I care about, namely the [lack of per-session locking](https://github.com/phpredis/phpredis/issues/37) and the [impossibility to run it in "strict" mode](https://github.com/phpredis/phpredis/issues/37).


### Session Locking explained

```
<?php

// test-project/web/visit-counter.php

session_start();

if (!isset($_SESSION['visits'])) {
  $_SESSION['visits'] = 0;
}

$_SESSION['visits']++;

echo $_SESSION['visits'];
```

```
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

1ma@werkbox:~$ hey -H "Cookie: PHPSESSID=9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21;" http://localhost/visit-counter.php
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

BUT:

```
127.0.0.1:6379> KEYS *
1) "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"

127.0.0.1:6379> GET PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21
"visits|i:134;"
```

```
1485174643.241711 [0 172.21.0.2:49780] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.241891 [0 172.21.0.2:49782] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.242444 [0 172.21.0.2:49782] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:129;"
1485174643.242878 [0 172.21.0.2:49780] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:129;"
1485174643.244780 [0 172.21.0.2:49784] "GET" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21"
1485174643.245385 [0 172.21.0.2:49784] "SETEX" "PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21" "900" "visits|i:130;"
```


### Session Strict Mode explained

```
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
```

```
127.0.0.1:6379> keys *
1) "PHPREDIS_SESSION:madeupkey"

127.0.0.1:6379> GET PHPREDIS_SESSION:9mcjmlsh9gp0conq7i5rci7is8gfn6s0gh8r3eub3qpac09gnh21
"visits|i:1;"
```


## Running the tests
