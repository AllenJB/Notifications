<?php

namespace AllenJB\Notifications;

use AllenJB\Plates\Extension\Escape;
use League\Plates\Engine;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Headers;
use Slim\Http\Response;

class SlimErrorHandler extends ErrorHandler
{


    public static function setupSlim(&$container) : void
    {
        $container['errorHandler'] = function ($container) {
            return function ($request, $response, $exception) {
                return static::exceptionPsr7($exception, $request, $response);
            };
        };
        $container['phpErrorHandler'] = function ($container) {
            return function ($request, $response, $exception) {
                return static::exceptionPsr7($exception, $request, $response);
            };
        };
    }


    public static function exceptionPsr7(\Throwable $e, RequestInterface $request, ResponseInterface $response) : ResponseInterface
    {
        $n = new Notification('fatal', 'ErrorHandler', null, $e);
        Notifications::any($n);

        if (defined('ERROR_HANDLER_LOG')) {
            $logMsg = static::exceptionAsString($e)
                . "\n\nException methods:\n" . print_r(get_class_methods($e), true)
                . "\n\nException properties:\n" . print_r(get_object_vars($e), true);

            file_put_contents(ERROR_HANDLER_LOG, $logMsg, FILE_APPEND);
        }


        // Check if headers already sent - if so, we're probably already in the middle of output
        // Check content type - If it's something other than html(/text/json) we should probably not display an error
        if (headers_sent()) {
            if (in_array(static::getOutputFormat(), ['text', 'html', 'json'], true)) {
                $errorResponse = new Response(500, new Headers());
                $errorResponse = $errorResponse->write('An error occurred. The developers have been notified.');
                return $errorResponse;
            }

            print "\nUncaught Exception: {$e->getMessage()}"
                . "\nLocation: {$e->getFile()} @ line {$e->getLine()}"
                . "\n\n{$e->getTraceAsString()}\n";
            exit(1);
        }

        // Return a new error page response, wiping any existing response content
        // TODO View location should be configured somehow (during setup?)
        if (in_array(static::getOutputFormat(), ['text', 'html', 'json'], true)) {
            $errorResponse = new Response(500, new Headers());
            $templates = new Engine(static::$projectRoot . '/App/Views');
            $templates->loadExtension(new Escape());
            $errorResponse = $errorResponse->write($templates->render('Error/Server'));
            return $errorResponse;
        }

        print "\nUncaught Exception: {$e->getMessage()}"
            . "\nLocation: {$e->getFile()} @ line {$e->getLine()}"
            . "\n\n{$e->getTraceAsString()}\n";
        exit(1);
    }

}
