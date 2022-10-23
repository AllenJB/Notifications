<?php

namespace AllenJB\Notifications;

class ErrorHandler
{

    /**
     * @var array Human descriptions of error levels
     */
    protected static $levels = [
        // Fatal
        E_ERROR => 'Error',
        E_PARSE => 'Parsing Error',
        E_CORE_ERROR => 'Core Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_USER_ERROR => 'User Error',

        // Non-Fatal
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
    ];


    protected static $projectRoot = "";

    protected static NotificationFactoryInterface $notificationFactory;

    protected static int $softMemoryLimitBytes;

    protected static Notifications $notifications;


    public static function setup(
        string $projectRoot,
        Notifications $notifications,
        NotificationFactoryInterface $notificationFactory
    ): void {
        static::$projectRoot = $projectRoot;
        static::$notifications = $notifications;
        static::$notificationFactory = $notificationFactory;
    }


    public static function setupHandlers(): void
    {
        if (! isset(static::$notifications)) {
            trigger_error('ErrorHandler::setup has not been run', E_USER_WARNING);
        }
        set_error_handler([__CLASS__, 'phpError']);
        set_exception_handler([__CLASS__, 'uncaughtException']);
        register_shutdown_function([__CLASS__, 'handleShutdown']);
    }


    /** @noinspection PhpMissingBreakStatementInspection */
    protected static function iniToBytes(string $iniValue): int
    {
        $iniValue = trim($iniValue);
        $last = strtolower(substr($iniValue, -1));
        if (! preg_match('/^[0-9]$/', $last)) {
            $iniValue = substr($iniValue, 0, -1);
        }
        switch ($last) {
            // Intentional fall-through
            case 'g':
                $iniValue *= 1024;
            case 'm':
                $iniValue *= 1024;
            case 'k':
                $iniValue *= 1024;
        }

        return $iniValue;
    }


    /**
     * We check for a Content-Type header, and only if one isn't found (or is found and appears to be HTML) do we assume HTML
     */
    protected static function getOutputFormat(): string
    {
        if (static::isCliRequest()) {
            return 'cli';
        }

        if (isset($_SERVER["HTTP_ACCEPT"]) && is_string($_SERVER["HTTP_ACCEPT"])) {
            $htmlTypes = [
                "text/html",
                "application/xhtml+xml",
            ];
            $jsonTypes = [
                "application/json",
            ];

            $qParts = explode(";", $_SERVER["HTTP_ACCEPT"]);
            foreach ($qParts as $qPart) {
                $types = explode(",", $qPart);
                foreach ($types as $type) {
                    if (strpos($type, "q=") === 0) {
                        continue;
                    }

                    if (in_array($type, $htmlTypes, true)) {
                        return "html";
                    }
                    if (in_array($type, $jsonTypes, true)) {
                        return "json";
                    }
                }
            }
        }

        $headers = headers_list();
        if (is_array($headers)) {
            foreach ($headers as $header) {
                $headerParts = explode(':', $header, 2);
                if (count($headerParts) < 2) {
                    continue;
                }

                $key = $headerParts[0];
                $value = trim($headerParts[1]);

                if ($key === 'Content-Type') {
                    if (stripos($value, 'application/json') !== false) {
                        return 'json';
                    } elseif (stripos($value, 'text/html') !== false) {
                        return 'html';
                    } elseif (stripos($value, 'text/') !== false) {
                        return 'text';
                    } else {
                        return 'other';
                    }
                }
            }
        }

        return 'other';
    }


    protected static function isCliRequest(): bool
    {
        return (PHP_SAPI === 'cli' || defined('STDIN'));
    }


    protected static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }


    /**
     * Display a generic error page
     */
    protected static function displayError(): void
    {
        $requestUri = ($_SERVER['REQUEST_URI'] ?? '');
        $doNotRedirect = (strpos($requestUri, '/error') === 0);

        switch (static::getOutputFormat()) {
            case 'html':
                if (! ($doNotRedirect || headers_sent())) {
                    header("Location: /error");
                    print " ";
                } else {
                    print '<p>A technical fault has occurred. The developers have been notified. Please contact support if the issue persists.</p>';
                }
                break;

            case 'json':
                if (! headers_sent()) {
                    http_response_code(500);
                }
                $response = [
                    'status' => 'error',
                    'error' => 'internal_server_error',
                    'error_message' => "A technical fault has occurred. The developers have been notified. Please contact support if the issue persists.",
                ];
                print json_encode($response);
                break;

            case 'text':
                if (! ($doNotRedirect || headers_sent())) {
                    header("Location: /error");
                    print " ";
                } else {
                    print 'A technical fault has occurred. The developers have been notified. Please contact support if the issue persists.';
                }
                break;

            case 'cli':
                print 'A technical fault has occurred. The developers have been notified. Please contact support if the issue persists.';
                break;

            case 'other':
            default:
                // Do nothing
                break;
        }
    }


    /**
     * Generate a stringified StackTrace suitable for display.
     * @param array $backtrace Backtrace generated by debug_backtrace()
     * @return string
     */
    public static function stackTraceString(array $backtrace = null): string
    {
        $stacktrace = "";
        if ($backtrace === null) {
            $backtrace = debug_backtrace();
        }

        $callDefaults = [
            "file" => "",
            "line" => "",
            "class" => "",
            "function" => "",
            "args" => [],
        ];

        foreach ($backtrace as $index => $call) {
            $index++;

            $call = array_merge($callDefaults, $call);

            if (
                (static::$projectRoot !== "")
                && (stripos(($call['file'] ?? ""), static::$projectRoot) === 0)
            ) {
                $call['file'] = str_replace(static::$projectRoot, '', $call['file']);
            }

            $args = "";
            if (is_array($call['args'])) {
                foreach ($call['args'] as $arg) {
                    if ($args !== "") {
                        $args .= ", ";
                    }

                    if (is_object($arg)) {
                        $args .= get_class($arg);
                    } elseif (is_array($arg)) {
                        $args .= 'Array[]';
                    } elseif (is_string($arg)) {
                        $args .= '"' . $arg . '"';
                    } elseif (is_bool($arg)) {
                        $args .= ($arg ? 'TRUE' : 'FALSE');
                    } elseif ($arg === null) {
                        $args .= 'NULL';
                    } else {
                        $args .= $arg;
                    }
                }
            }

            $keys = ['class', 'function', 'file', 'line'];
            foreach ($keys as $key) {
                if (! array_key_exists($key, $call)) {
                    $call[$key] = '';
                }
            }

            $stacktrace .= $index . ': ' . $call['class'] . '::' . $call['function'] . "($args)"
                . "\n\t" . $call['file'] . '(' . $call['line'] . ')'
                . "\n";
        }

        return $stacktrace;
    }


    public static function handleShutdown(): void
    {
        static::handleShutdownError();
        static::handleShutdownMemory();
    }


    /**
     * Note: We explicitly avoid using outside code as we may have hit memory limit and have very little to work with
     */
    protected static function handleShutdownError(): void
    {
        if (! (isset(static::$notificationFactory) && isset(static::$notifications))) {
            return;
        }
        $lastError = error_get_last();
        if (! (is_array($lastError) && array_key_exists('type', $lastError))) {
            return;
        }
        $reportLevels = [E_PARSE, E_COMPILE_ERROR, E_COMPILE_WARNING, E_CORE_ERROR, E_CORE_WARNING, E_ERROR];
        if (! in_array($lastError['type'], $reportLevels, true)) {
            return;
        }

        $exception = new \ErrorException(
            $lastError['message'],
            0,
            $lastError['type'],
            $lastError['file'],
            $lastError['line']
        );
        $n = static::$notificationFactory::fromThrowable($exception, "Shutdown Error Handler");
        static::$notifications->send($n);
    }


    protected static function setSoftMemoryLimitBytes(string $iniFormat): void
    {
        static::$softMemoryLimitBytes = static::iniToBytes($iniFormat);
    }


    protected static function setSoftMemoryLimitPercentage(int $percent): void
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = static::iniToBytes($memoryLimit);
        static::$softMemoryLimitBytes = $memoryLimitBytes * ($percent / 100);
    }


    /**
     * Check the amount of memory used by the request and report if it's close to the memory limit
     * Note: We explicitly avoid using outside code as we may be near memory limit
     */
    protected static function handleShutdownMemory(): void
    {
        if (! isset(static::$notifications)) {
            return;
        }
        if (! isset(static::$softMemoryLimitBytes)) {
            return;
        }

        $memoryUsed = memory_get_peak_usage(true);
        if ($memoryUsed < static::$softMemoryLimitBytes) {
            return;
        }

        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = static::iniToBytes($memoryLimit);
        $n = new Notification("warning", "Soft Memory Limit", "Soft Memory Limit Reached");
        $n->addContext("soft_limit_bytes", number_format(static::$softMemoryLimitBytes));
        $n->addContext("memory_usage_bytes", number_format($memoryUsed));
        $n->addContext("memory_limit_ini", $memoryLimit);
        $n->addContext("memory_limit_bytes", number_format($memoryLimitBytes));

        static::$notifications->send($n);
    }


    /*
     * Native PHP error handler
     */
    public static function phpError(int $severity, string $message, string $filepath = null, int $line = null): bool
    {
        if (! (error_reporting() & $severity)) {
            return true;
        }

        $severityDesc = (static::$levels[$severity] ?? $severity);
        // Some errors can be displayed inline and attempt to continue the code
        $inlineLevels = [E_STRICT, E_NOTICE, E_WARNING, E_USER_NOTICE, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED];
        $isInlineError = in_array($severity, $inlineLevels, true);

        if (isset(static::$notificationFactory) && isset(static::$notifications)) {
            $e = new \ErrorException($message, 0, $severity, $filepath, $line);
            $n = static::$notificationFactory::fromThrowable($e, "PHP Error");
            static::$notifications->send($n);
        }

        if (static::getOutputFormat() === 'cli') {
            print "\n\n{$severityDesc}: {$message}"
                . "\nLocation: {$filepath} @ line {$line}"
                . "\n\nSTACK TRACE:\n" . static::stackTraceString()
                . "\n";
        } elseif (! $isInlineError) {
            static::displayError();
            exit(1);
        }

        // Invoke the standard PHP error handler
        return false;
    }


    public static function uncaughtException(\Throwable $e): void
    {
        if (isset(static::$notificationFactory) && isset(static::$notifications)) {
            $n = static::$notificationFactory::fromThrowable($e, "Uncaught Exception");
            static::$notifications->send($n);
        }

        if (static::getOutputFormat() === 'cli') {
            $stacktrace = $e->getTraceAsString();
            $line = $e->getLine();
            $message = $e->getMessage();
            $filepath = $e->getFile();

            print "\nUncaught Exception: {$message}"
                . "\nLocation: {$filepath} @ line {$line}"
                . "\n\n{$stacktrace}\n";
        } else {
            static::displayError();
        }

        exit(1);
    }


    /**
     * Return a list of all error levels, showing whether they're enabled or not, for a given error_reporting value
     * @param int $errorReporting
     * @return bool[]
     */
    protected static function enabledLevels(int $errorReporting): array
    {
        return [
            'E_ERROR' => (($errorReporting & E_ERROR) === E_ERROR),
            'E_WARNING' => (($errorReporting & E_WARNING) === E_WARNING),
            'E_PARSE' => (($errorReporting & E_PARSE) === E_PARSE),
            'E_NOTICE' => (($errorReporting & E_NOTICE) === E_NOTICE),
            'E_CORE_ERROR' => (($errorReporting & E_CORE_ERROR) === E_CORE_ERROR),
            'E_CORE_WARNING' => (($errorReporting & E_CORE_WARNING) === E_CORE_WARNING),
            'E_COMPILE_ERROR' => (($errorReporting & E_COMPILE_ERROR) === E_COMPILE_ERROR),
            'E_COMPILE_WARNING' => (($errorReporting & E_COMPILE_WARNING) === E_COMPILE_WARNING),
            'E_USER_ERROR' => (($errorReporting & E_USER_ERROR) === E_USER_ERROR),
            'E_USER_WARNING' => (($errorReporting & E_USER_WARNING) === E_USER_WARNING),
            'E_USER_NOTICE' => (($errorReporting & E_USER_NOTICE) === E_USER_NOTICE),
            'E_STRICT' => (($errorReporting & E_STRICT) === E_STRICT),
            'E_RECOVERABLE_ERROR' => (($errorReporting & E_RECOVERABLE_ERROR) === E_RECOVERABLE_ERROR),
            'E_DEPRECATED' => (($errorReporting & E_DEPRECATED) === E_DEPRECATED),
            'E_USER_DEPRECATED' => (($errorReporting & E_USER_DEPRECATED) === E_USER_DEPRECATED),
            'E_ALL' => (($errorReporting & E_ALL) === E_ALL),
        ];
    }

}
