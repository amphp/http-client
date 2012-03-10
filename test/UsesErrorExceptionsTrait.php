<?php

trait UsesErrorExceptionsTrait
{
    protected function setUpErrorHandler()
    {
        error_reporting(E_ALL);
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $levels = [
                E_WARNING           => 'Warning',
                E_NOTICE            => 'Notice',
                E_USER_ERROR        => 'User Error',
                E_USER_WARNING      => 'User Warning',
                E_USER_NOTICE       => 'User Notice',
                E_STRICT            => 'Runtime Notice',
                E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
                E_DEPRECATED        => 'Deprecated Notice',
                E_USER_DEPRECATED   => 'User Deprecated Notice'
              ];
            $msg = $levels[$errno] . ": $errstr in $errfile on line $errline";
            throw new \ErrorException($msg);
        });
    }
    
    protected function tearDownErrorHandler()
    {
        restore_error_handler();
        error_reporting(E_ALL);
        ini_set('display_errors', TRUE);
    }
}
