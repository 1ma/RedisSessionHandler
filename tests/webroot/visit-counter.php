<?php

// GET /visit-counter.php initialises a session and returns the number
// of requests already made by a given user.
// This is the main test script that exercises the APCuSessionHandler.

require_once __DIR__.'/../../vendor/autoload.php';

// 1maa/php-fpm Docker images come with Secure cookies enabled by default. Turning this
// off allows full-blown web browsers to resend cookies over a plain HTTP connection.
ini_set('session.cookie_secure', '0');

// session_set_save_handler(new \UMA\RedisSessionHandler(), true);

session_start();

if (!isset($_SESSION['visits'])) {
    $_SESSION['visits'] = 0;
}

echo ++$_SESSION['visits'];
