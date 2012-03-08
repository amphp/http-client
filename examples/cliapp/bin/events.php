#!/usr/bin/php
<?php

define('AX_DEBUG_FLAG', TRUE); // optional -- defaults to TRUE if not defined
require '/path/to/your/bootstrap/Artax.php';
// --- END FRAMEWORK SETUP




// add some listeners
$listeners = new StdClass;
$listeners->ready = [
    function() {
        echo "[ready] I'm 'ready' listener #1" . PHP_EOL;
    },
    function() use ($axMed) {
        echo "[ready] I'm 'ready' listener #2" . PHP_EOL;
        $axMed->notify('another_event');
        return FALSE;
    },
    function() {
        echo "[ready] I'm a sad listener because you'll never see me.";
    }
];
$listeners->another_event = function() {
    echo '[another_event] I was notified by [ready] listener #2!' . PHP_EOL;
};
$axMed->pushAll($listeners);

// let's fire this baby up!
$axMed->notify('ready');
