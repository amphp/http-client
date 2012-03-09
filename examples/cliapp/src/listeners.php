<?php

$listeners = new StdClass;
$listeners->ready = [
    function() {
        echo "[ready] I'm listener #1" . PHP_EOL;
    },
    function() use ($artax) {
        echo "[ready] I'm listener #2" . PHP_EOL;
        $artax->notify('another_event');
        return FALSE;
    },
    function() {
        echo "[ready] I'm listener #3, but you'll never see me because listener"
            .' #2 returns FALSE';
    }
];
$listeners->another_event = function() {
    echo '[another_event] I was notified by listener #2!' . PHP_EOL;
};
