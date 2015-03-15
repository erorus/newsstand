<?php

$uri = $_SERVER['REQUEST_URI'];

header('HTTP/1.1 301 Moved Permanently');
header('Location: https://theunderminejournal.com' . $uri);
