<?php

// GET /visit-counter.php initialises a session and returns the number
// of requests already made by a given authenticated client.
// This is the main test script that exercises the RedisSessionHandler.

require_once __DIR__.'/../../vendor/autoload.php';

if (isset($_GET['with_no_time_limit'])) {
    set_time_limit(0);
}

session_set_save_handler(new \UMA\RedisSessionHandler(), true);

session_start();

if (!isset($_SESSION['visits'])) {
    $_SESSION['visits'] = 0;
}

echo ++$_SESSION['visits'];
