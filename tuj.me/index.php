<?php

if ($_SERVER['SERVER_NAME'] == 'b.tuj.me') {
    header('HTTP/1.1 302 Found');
    header('Location: https://blog.theunderminejournal.com/');
    exit;
}

header('Location: https://theunderminejournal.com/');

