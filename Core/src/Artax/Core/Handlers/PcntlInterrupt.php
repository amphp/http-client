<?php

/**
 * Artax Process Control Interrupt Handler Class File
 *
 * PHP version 5.4
 *
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace Artax\Core\Handlers;

/**
 * Handles Termination From Process Control Interrupts
 * 
 * Handles unix-style SIGTERM, SIGHUP, SIGINT and SIGQUIT signals to throw an
 * Artax\Exceptions\PcntlInterruptException when script termination is requested
 * using a PCNTL signal.
 * 
 * This handler is only available in Unix-style operating systems (not windows)
 * in the CLI SAPI and is automatically registered in the Artax.php bootstrap
 * file if the necessary pre-conditions are met.
 * 
 * Exception event listeners can determine if the uncaught exception was the
 * result of a process control event by performing an `instanceof` check on the
 * uncaught exception object like so:
 * 
 * ```php
 * $listener = function(Exception $e, $debugFlag) {
 *     if ($e instanceof Artax\Exceptions\PcntlInterruptException) {
 *         // do something specific for process control interrupts
 *     } else {
 *         // do something else for other uncaught exceptions
 *     }
 * };
 * ```
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class PcntlInterrupt
{
    /**
     * A list of terminating PCNTL Signals that can be handled
     * @var array
     */
    protected $signals = [
        SIGTERM => 'SIGTERM',
        SIGHUP  => 'SIGHUP',
        SIGINT  => 'SIGINT',
        SIGQUIT => 'SIGQUIT'            
    ];
    
    /**
     * Declares ticks. Yep, that's it.
     * 
     * @return void
     */
    public function __construct()
    {
        declare(ticks = 1);
    }
    
    /**
     * Throws a PcntlInterruptException when process termination requested
     * 
     * @param int $sigNo The integer process control signal
     * 
     * @return void
     * @throws PcntlInterruptException On process interrupt request
     */
    public function handle($sigNo)
    {
        $sigName = isset($this->signals[$sigNo])
            ? $this->signals[$sigNo]
            : 'UNKNOWN PCNTL SIGNAL';
        throw new PcntlInterruptException(
            "Process termination requested by $sigName"
        );
    }
    
    /**
     * Registers the handler for PCNTL termination signals
     * 
     * Each of the signals listed in the object's protected $signals property
     * will be registered to use PcntlInterrupt::handle as their handler.
     * 
     * @return void
     */
    public function register()
    {
        foreach ($this->signals as $int => $name) {
            pcntl_signal($int, [$this, 'handle']);
        }
    }
}
