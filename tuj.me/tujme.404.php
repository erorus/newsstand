<?php

if ($_SERVER['SERVER_NAME'] == 'b.tuj.me') {
    if (preg_match('/^\/(\d+)$/',$_SERVER['REQUEST_URI'],$res) > 0) {
        header('HTTP/1.1 302 Found');
        header('Location: https://blog.theunderminejournal.com/?itemid='.$res[1]);
    } else {
        header('HTTP/1.1 404 Not Found');
    }
    exit;
}

$regs = array();
$regs['/^\/TUJTooltip$/'] = 'http://stormspire.net/official-forum-undermine-journal/3923-auctioneer-tuj-%3D-auc-stat-theunderminejournal.html#post37375';
$regs['/^\/Contact$/'] = 'https://theunderminejournal.com/#contact';

foreach ($regs as $re => $url)
    if (preg_match($re,$_SERVER['REQUEST_URI'],$res) > 0) {
        header('Location: '.$url);
        exit;
    }

header('Location: https://theunderminejournal.com/');

