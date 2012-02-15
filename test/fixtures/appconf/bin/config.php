<?php

$cfg = array();

//$cfg['smarty'] = TRUE;

$cfg['phpar'] = TRUE;


// custom app_dirs
$cfg['app_dir_vendors'] = 'test/fixtures/test_app_path/vendors';


/*
 * !!! THIS DIRECTIVE MUST BE SPECIFIED FOR WEB APPS TO FUNCTION CORRECTLY !!!
 * 
 * If you fail to specify the 'fc_url' directive web apps will not
 * function. If you are only using Rasmus Init for CLI purposes you
 * may safely leave out this directive.
 * 
 * The base URL where the app is the web address of your index.php
 * front controller. A trailing slash is optional as it is added
 * automatically when the directive is set.
 */
//$cfg['fc_url'] = 'http://www.mydomain.com/myapp/';



/*
 * --- OPTIONAL DIRECTIVES ---
 */

/* 
 * Controls whether or not debug output is displayed on-screen.
 * This directive should always be set to FALSE in production
 * environments.
 * 
 * The default value is FALSE.
 */
$cfg['debug'] = TRUE;

/*
 * The default timezone for dates. If this directive is not
 * set the framework will default to GMT.
 * e.x. $cfg['timezone'] = 'EDT';
 */ 
$cfg['timezone'] = 'GMT';

/*
 * The "last chance" exception handling function is the last line
 * of defense against uncaught exceptions. This is very important because
 * the default Rasmus error handler throws exceptions for any PHP error
 * encountered when not in DEBUG mode.
 * 
 * This function will usually call some sort of "Internal Server Error"
 * display showing debug output in development environments and
 * a user-friendly message in production environments. It's important
 * to make sure the function has been declared prior to config loading.
 * In this case the configuration autoload directive can be helpful.
 * 
 * This directive cannot be changed during program execution.
 * 
 * e.x. $cfg['ex_handler'] = '\MyLib\Utils\exception_handler';
 */ 
//$cfg['ex_handler'] = '\Rasmus\ex_handler';


/*
 * User defined function for handling PHP errors. If not specified,
 * the default Rasmus error handler will be used. The default handler
 * throws an exception on any PHP error when not in DEBUG mode, so you
 * should specify your own function if this behavior causes you angst.
 * It's important to make sure the function has been declared prior to
 * config loading. In this case the configuration autoload directive
 * can be helpful.
 * 
 * This directive cannot be changed during program execution.
 * 
 * e.x. $cfg['err_handler'] = '\MyLib\Utils\error_handler';
 */
//$cfg['err_handler'] = '\Rasmus\err_handler';

/*
 * Whether or not to parse the config/autoload file's $autoload
 * array for files to require at initialization. If this directive
 * is FALSE or not set, the autoload file will not be automaticaly
 * read. Specifying this directive as TRUE and using the autoload
 * array from the config/autoload file is the preferred method to
 * include your custom error handling functions.
 * 
 * The default value is FALSE.
 * This directive cannot be changed during program execution.
 */
//$cfg['autoload'] = TRUE;




/*
 * --- CUSTOM DIRECTIVES ---
 * 
 * Custom config directives are not filtered or sanitized in any
 * way, so it's up to you to ensure that no harmful data is present
 * in your custom config directives.
 */

?>