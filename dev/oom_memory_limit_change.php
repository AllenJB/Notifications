<?php
/*
 * This example file demonstrates changing memory_limit in a shutdown function after the memory limit has been hit
 * This allows you to give code extra memory to perform error reporting
 */
ini_set('memory_limit', '2M');

function shutdownHandler()
{
    print "Shutdown handler triggered\n";
    print "Memory limit: ". ini_get('memory_limit') ."\n";
    // If the next line is commented, a second OOM will be triggered
    ini_set('memory_limit', '-1');
    print "Memory limit: ". ini_get('memory_limit') ."\n";
    print "Mem usage: ". memory_get_usage(true) ."\n";

    $a = '';
    $a .= str_repeat("Hello", 1024 * 1024);
    print "Mem usage: ". memory_get_usage(true) ."\n";
}
register_shutdown_function('shutdownHandler');

print "Memory Limit is: ". ini_get('memory_limit');

$a = '';
while (true) {
    $a .= str_repeat("Hello", 1024 * 1024);
}
