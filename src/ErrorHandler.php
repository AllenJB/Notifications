<?php

namespace AllenJB\Notifications;

class ErrorHandler
{

    protected static $devEmails = [];

    /**
     * @var int If $_REQUEST exceeds this value (in bytes), then do not output to email
     */
    protected static $requestSizeLimit = 2048;

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

    protected static $projectName = "";

    protected static $appEnvironment = "";


    public static function setup(string $projectRoot, string $projectName, string $appEnvironment): void
    {
        static::$projectRoot = $projectRoot;
        static::$projectName = $projectName;
        static::$appEnvironment = $appEnvironment;

        set_error_handler([__CLASS__, 'phpError']);
        set_exception_handler([__CLASS__, 'uncaughtException']);
        register_shutdown_function([__CLASS__, 'handleShutdown']);
    }


    public static function setupNoHandlers(string $projectRoot, string $projectName, string $appEnvironment): void
    {
        static::$projectRoot = $projectRoot;
        static::$projectName = $projectName;
        static::$appEnvironment = $appEnvironment;
    }


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


    protected static function email(string $msg, string $subject, bool $addStackTrace = true): void
    {
        // We provide a fallback value for DEVELOPER_EMAILS just in case it's not defined for any reason
        $emails = static::$devEmails;
        if (defined('DEVELOPER_EMAILS')) {
            $emails = DEVELOPER_EMAILS;
        }
        if (count($emails) < 1) {
            return;
        }
        if (is_array($emails)) {
            $emails = implode(',', $emails);
        }

        if ($addStackTrace) {
            $stacktrace = static::stackTraceString();
            $msg .= "\n\nStack trace:\n" . $stacktrace;
        }

        if (defined('APP_VERSION')) {
            $msg .= "\n\nApp Version: " . APP_VERSION;
        }

        $msg .= "\n\n"
            . "_SESSION:\n" . (isset($_SESSION) ? print_r($_SESSION, true) : 'UNSET') . "\n\n"
            . "_SERVER:\n" . print_r($_SERVER, true) . "\n\n";

        $requestDump = print_r($_REQUEST, true);
        if (strlen($requestDump) < static::$requestSizeLimit) {
            $msg .= "\n\n_REQUEST:\n" . $requestDump;
        } else {
            $msg .= "\n\n_REQUEST: (excluded due to size)";
        }

        $msg .= "\n\n--- EOM ---\n";
        $subject .= (static::$appEnvironment !== 'production' ? ' - ' . static::$appEnvironment : '')
            . ' - ' . static::$projectName;
        @mail($emails, $subject, $msg);
    }


    /**
     * @return string
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

                    if (in_array($type,$htmlTypes, true)) {
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
                if (! (headers_sent() || $doNotRedirect)) {
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
                if (! (headers_sent() || $doNotRedirect)) {
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

            if (static::$projectRoot !== "") {
                if (array_key_exists('file', $call) && (stripos($call['file'], static::$projectRoot) === 0)) {
                    $call['file'] = str_replace(static::$projectRoot, '', $call['file']);
                }
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


    public static function exceptionAsString(\Throwable $e): string
    {
        $email = "Message: {$e->getMessage()}"
            . "\nType: " . get_class($e)
            . "\nCode: " . $e->getCode()
            . "\nLine: " . $e->getLine()
            . "\nFile: " . $e->getFile()
            . "\nStack Trace:\n" . $e->getTraceAsString()
            . "\n\n";

        if (is_a($e, '\SubTech\CsvException')) {
            $email .= "\nCSV Line No: " . $e->csvLineNo
                . "\nHeaders: " . print_r($e->csvHeaders, true)
                . "\nRecord: " . print_r($e->csvLine, true);
        }

        if (is_a($e, '\AllenJB\Sql\Exception\DatabaseQueryException') || is_a(
                $e,
                '\SubTech\Sql\DatabaseQueryException'
            )) {
            $email .= "\nStatement:\n" . print_r($e->getStatement(), true);
            $email .= "\n\nValues:\n" . print_r($e->getValues(), true);
        }

        if (is_a($e, '\GuzzleHttp\Exception\TransferException')) {
            if (is_object($e->getResponse())) {
                $email .= "\nResponse Body:\n" . $e->getResponse()->getBody()->getContents();
                $email .= "\n\nResponse Headers:\n" . print_r($e->getResponse()->getHeaders(), true);
            }
        }

        if (is_object($e->getPrevious())) {
            $email .= "--- Previous Exception ---\n"
                . static::exceptionAsString($e->getPrevious());
        }

        return $email;
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
        $lastError = error_get_last();
        if (! (is_array($lastError) && array_key_exists('type', $lastError))) {
            return;
        }
        $reportLevels = [E_PARSE, E_COMPILE_ERROR, E_COMPILE_WARNING, E_CORE_ERROR, E_CORE_WARNING, E_ERROR];
        if (! in_array($lastError['type'], $reportLevels, true)) {
            return;
        }

        $sendEmail = true;
        if (class_exists(LoggingService::class)) {
            $service = LoggingService::getInstance();
            if ($service !== null) {
                $exception = new \ErrorException(
                    $lastError['message'],
                    0,
                    $lastError['type'],
                    $lastError['file'],
                    $lastError['line']
                );
                $event = new LoggingServiceEvent($exception);
                $event->setLogger('shutdown_handler');
                $id = $service->send($event);
                if (! empty($id)) {
                    $sendEmail = false;
                }
            }
        }

        $msg = "Shutdown Handler Error Report\n"
            . "Error:\n" . print_r($lastError, true);
        if ($sendEmail) {
            static::email($msg, 'Shutdown PHP Error');
        }

        if (defined('ERROR_HANDLER_LOG')) {
            file_put_contents(ERROR_HANDLER_LOG, $msg, FILE_APPEND);
        }
    }


    /**
     * Check the amount of memory used by the request and report if it's close to the memory limit
     * Note: We explicitly avoid using outside code as we may be near memory limit
     */
    protected static function handleShutdownMemory(): void
    {
        $warnPercentage = 0.75;
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = static::iniToBytes($memoryLimit);
        $softLimitBytes = $memoryLimitBytes * $warnPercentage;

        $memoryUsed = memory_get_peak_usage(true);
        if ($memoryUsed < $softLimitBytes) {
            return;
        }

        $sendEmail = true;
        if (class_exists(LoggingService::class)) {
            $service = LoggingService::getInstance();
            if ($service !== null) {
                $event = new LoggingServiceEvent("Memory Limit Soft Limit Reached");
                $event->setLevel('warning');
                $event->setContext(
                    [
                        'soft_limit' => number_format($softLimitBytes),
                        'memory_usage' => number_format($memoryUsed),
                    ]
                );
                $event->setLogger('shutdown_handler');
                $id = $service->send($event);
                if (! empty($id)) {
                    $sendEmail = false;
                }
            }
        }

        if ($sendEmail) {
            $msg = "Memory Soft Limit Reached"
                . "\nAll values are in bytes"
                . "\nMemory Limit: " . number_format($memoryLimitBytes) . ' (' . $memoryLimit . ')'
                . "\nSoft Limit:   " . number_format($softLimitBytes)
                . "\nMemory Usage: " . number_format($memoryUsed);
            static::email($msg, 'Memory Soft Limit Reached');
        }
    }


    /*
     * Native PHP error handler
     */
    public static function phpError(int $severity, string $message, string $filepath = null, int $line = null): bool
    {
        if (($severity & error_reporting()) !== $severity) {
            return true;
        }

        $severityDesc = (static::$levels[$severity] ?? $severity);
        // Some errors can be displayed inline and attempt to continue the code
        $inlineLevels = [E_STRICT, E_NOTICE, E_WARNING, E_USER_NOTICE, E_USER_WARNING];
        $isInlineError = in_array($severity, $inlineLevels, true);

        $stacktrace = static::stackTraceString();

        $notifyLevel = [
            E_STRICT => 'warning',
            E_NOTICE => 'warning',
            E_WARNING => 'warning',
            E_USER_NOTICE => 'warning',
            E_USER_WARNING => 'warning',
        ];
        $nLevel = (array_key_exists($severity, $notifyLevel) ? $notifyLevel[$severity] : 'error');
        $e = new \ErrorException($message, 0, $severity, $filepath, $line);
        $n = new Notification($nLevel, 'ErrorHandler', null, $e);
        Notifications::any($n);

        $logMsg = "Severity: {$severityDesc}"
            . "\nMessage: {$message}"
            . "\nFilename: {$filepath}"
            . "\nLine: {$line}";

        if (defined('ERROR_HANDLER_LOG')) {
            file_put_contents(ERROR_HANDLER_LOG, $logMsg, FILE_APPEND);
        }

        if (static::getOutputFormat() === 'cli') {
            $msg = "\n\n{$severityDesc}: {$message}"
                . "\nLocation: {$filepath} @ line {$line}"
                . "\n\nSTACK TRACE:\n" . $stacktrace
                . "\n";
            print $msg;
        } elseif (! $isInlineError) {
            static::displayError();
            exit(1);
        }

        // Invoke the standard PHP error handler
        return false;
    }


    public static function uncaughtException(\Throwable $e): void
    {
        // Set variables used in php_error template
        $stacktrace = $e->getTraceAsString();
        $line = $e->getLine();
        $message = $e->getMessage();
        $filepath = $e->getFile();

        $n = new Notification('fatal', 'ErrorHandler', null, $e);
        if (is_a($e, '\AllenJB\Sql\Exception\DatabaseQueryException') || is_a(
                $e,
                '\SubTech\Sql\DatabaseQueryException'
            )) {
            $n->addContext("query statement", $e->getStatement());
            $n->addContext("query values", $e->getValues());
        }
        Notifications::any($n);

        $logMsg = static::exceptionAsString($e)
            . "\n\nException methods:\n" . print_r(get_class_methods($e), true)
            . "\n\nException properties:\n" . print_r(get_object_vars($e), true);

        if (defined('ERROR_HANDLER_LOG')) {
            file_put_contents(ERROR_HANDLER_LOG, $logMsg, FILE_APPEND);
        }

        if (static::getOutputFormat() === 'cli') {
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
