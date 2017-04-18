<?php

namespace AllenJB\Notifications;

use AllenJB\Mailer\Email;
use AllenJB\Mailer\Transport\AbstractTransport;

class Notifications
{

    /**
     * @var null|AbstractTransport
     */
    protected static $defaultTransport = null;

    protected static $devEmails = [];

    protected static $projectName = "";

    protected static $projectRoot = null;


    public static function setDefaultMailTransport(AbstractTransport $transport)
    {
        static::$defaultTransport = $transport;
    }


    public static function setDeveloperEmails(array $emails)
    {
        static::$devEmails = $emails;
    }


    public static function setProjectName($projectName)
    {
        static::$projectName = $projectName;
    }


    public static function setProjectRoot($rootPath)
    {
        static::$projectRoot = $rootPath;
    }


    public static function email($msg, $subject, \Exception $exception = null)
    {
        $msg = "Automated Notification:\n" . $msg;

        if (is_object($exception) && (($exception instanceof \Throwable) || ($exception instanceof \Exception))) {
            $msg .= "\n\n" . static::get_exception_stack_string($exception);
        } else {
            $msg .= "\n\nBacktrace:\n" . static::get_backtrace_string();
        }

        $msg .= "\n\n_SERVER: " . print_r($_SERVER, true);

        $requestDump = print_r($_REQUEST, true);
        if (strlen($requestDump) < 512) {
            $msg .= "\n\n_REQUEST:\n" . $requestDump;
        } else {
            $msg .= "\n\n_REQUEST: (excluded due to size)";
        }
        $msg .= "\n\n--- EOM ---\n";
        $subject = static::$projectName . " Notification: " . $subject;

        if (count(static::$devEmails) < 1) {
            return;
        }

        if (static::$defaultTransport === null) {
            $emails = implode(',', static::$devEmails);
            mail($emails, $subject, $msg);
        } else {
            $email = new Email();
            $email->setSubject($subject);
            $email->setTextBody($msg);
            $email->setFrom(get_current_user() . '@' . gethostname());
            $email->addRecipientsTo(static::$devEmails);

            static::$defaultTransport->send($email);
        }
    }


    /**
     * Send a notification by the prefered channel
     *
     * @param Notification $notification
     */
    public static function any(Notification $notification)
    {
        $sendEmail = true;
        $service = LoggingService::getInstance();
        $msg = $notification->getMessage();
        if (is_object($service)) {
            if (is_object($notification->getException())) {
                $serviceEvent = new LoggingServiceEvent($notification->getException());
                if (!empty($msg)) {
                    $serviceEvent->setMessage($msg);
                }
            } else {
                $serviceEvent = new LoggingServiceEvent($msg);
            }
            $serviceEvent->setLevel($notification->getLevel());
            $serviceEvent->setContext($notification->getContext());
            $id = $service->send($serviceEvent);
            if (!empty($id)) {
                $sendEmail = false;
            }
        }

        if ($sendEmail) {
            if (empty($msg)) {
                $msg = '';
                if (is_object($notification->getException())) {
                    $msg = $notification->getException()->getMessage();
                }
            }
            $subject = $notification->getLogger() .' '. ucwords($notification->getLevel());

            foreach ($notification->getContext() as $key => $value) {
                $msg .="\n\n{$key}: ". print_r($value, true);
            }
            static::email($msg, $subject, $notification->getException());
        }
    }


    /**
     * @param \Exception $e
     * @return string
     */
    protected static function get_exception_stack_string(\Exception $e)
    {
        $email = "Message: {$e->getMessage()}"
            . "\nType: " . get_class($e)
            . "\nCode: " . $e->getCode()
            . "\nLine: " . $e->getLine()
            . "\nFile: " . $e->getFile();

        if (is_a($e, '\SubTech\CsvException')) {
            $email .= "\nCSV Line No: " . $e->csvLineNo
                . "\nHeaders: " . print_r($e->csvHeaders, true)
                . "\nRecord: " . print_r($e->csvLine, true);
        }

        if (is_a($e, '\AllenJB\Sql\DatebaseQueryException') || is_a($e, '\SubTech\Sql\DatabaseQueryException')) {
            $email .= "\nStatement:\n" . print_r($e->getStatement(), true);
            $email .= "\n\nValues:\n" . print_r($e->getValues(), true);
        }

        if (is_a($e, '\GuzzleHttp\Exception\TransferException')) {
            if (is_object($e->getResponse())) {
                $email .= "\nResponse Body:\n" . $e->getResponse()->getBody()->getContents();
                $email .= "\n\nResponse Headers:\n" . print_r($e->getResponse()->getHeaders(), true);
            }
        }

        $email .= ""
            . "\nStack Trace:\n" . static::get_backtrace_string($e->getTrace())
            . "\n\n";

        if (is_object($e->getPrevious())) {
            $email .= "--- Previous Exception ---\n"
                . static::get_exception_stack_string($e->getPrevious());
        }

        return $email;
    }


    protected static function get_backtrace_string(array $backtrace = null)
    {
        $stacktrace = "";
        if ($backtrace === null) {
            $backtrace = debug_backtrace();
        }
        $skipClassList = ['Notifications'];

        $callDefaults = [
            "file" => "",
            "line" => "",
            "class" => "",
            "function" => "",
            "args" => [],
        ];

        foreach ($backtrace as $index => $call) {
            $call = array_merge($callDefaults, $call);

            $index++;
            if (array_key_exists('class', $call) && in_array($call['class'], $skipClassList, true)) {
                $stacktrace = $index . ": {$call['class']} - Skipped\n";
                continue;
            }

            if (static::$projectRoot !== null) {
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
                    } else {
                        if (is_array($arg)) {
                            $args .= 'Array[]';
                        } else {
                            if (is_string($arg)) {
                                $args .= '"' . $arg . '"';
                            } else {
                                $args .= $arg;
                            }
                        }
                    }
                }
            }

            // Silence logged errors
            $keys = ['class', 'function', 'file', 'line'];
            foreach ($keys as $key) {
                if (! array_key_exists($key, $call)) {
                    $call[$key] = '';
                }
            }

            $stacktrace .= $index . ":\n\t"
                . $call['class'] . '::' . $call['function'] . "($args)"
                . "\n\t" . $call['file'] . '(' . $call['line'] . ')'
                . "\n";
        }

        return $stacktrace;
    }

}
