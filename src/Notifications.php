<?php

namespace AllenJB\Notifications;

use AllenJB\Mailer\Email;
use AllenJB\Mailer\Transport\AbstractTransport;

class Notifications
{

    protected static ?AbstractTransport $defaultTransport = null;

    protected static array $devEmails = [];

    protected static string $projectName = "";

    protected static ?string $senderEmail = null;

    protected static ?LoggingServiceInterface $loggingService = null;

    protected static int $requestSizeLimit = 2048;


    public static function setDefaultMailTransport(?AbstractTransport $transport): void
    {
        static::$defaultTransport = $transport;
    }


    public static function setLoggingService(?LoggingServiceInterface $loggingService): void
    {
        static::$loggingService = $loggingService;
    }


    public static function setDeveloperEmails(array $emails): void
    {
        static::$devEmails = $emails;
    }


    public static function setProjectName(string $projectName): void
    {
        static::$projectName = $projectName;
    }


    public static function setSender(string $senderEmail): void
    {
        static::$senderEmail = $senderEmail;
    }


    public static function emailSimple(string $msg, string $subject, \Throwable $exception = null): void
    {
        $msg = "Automated Notification:\n" . $msg;

        if (is_object($exception) && (($exception instanceof \Throwable) || ($exception instanceof \Exception))) {
            $msg .= "\n\n" . static::exceptionAsString($exception);
        } else {
            $msg .= "\n\nBacktrace:\n" . ErrorHandler::stackTraceString();
        }

        $msg .= "\n\n_SERVER: " . print_r($_SERVER, true);

        $requestDump = print_r($_REQUEST, true);
        if (strlen($requestDump) < static::$requestSizeLimit) {
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
            if (static::$senderEmail !== null) {
                $email->setFrom(static::$senderEmail);
            } else {
                $email->setFrom(get_current_user() . '@' . gethostname());
            }
            $email->addRecipientsTo(static::$devEmails);

            static::$defaultTransport->send($email);
        }
    }


    public static function email(Notification $notification): void
    {
        if (count(static::$devEmails) < 1) {
            return;
        }

        $msg = "Automated Notification:\n" . ($notification->getMessage() ?? "");

        $exception = $notification->getException();
        if ($exception !== null) {
            if ($notification->getMessage() !== null) {
                $msg .= "\n";
            }
            $msg .= $exception->getMessage();
        }

        foreach ($notification->getContext() as $key => $value) {
            $msg .= "\n\n{$key}: " . print_r($value, true);
        }

        if ($exception !== null) {
            $msg .= "\n\n" . static::exceptionAsString($exception);
        } else {
            $msg .= "\n\nBacktrace:\n" . ErrorHandler::stackTraceString();
        }

        $msg .= "\n\n_SERVER: " . print_r($_SERVER, true);

        $requestDump = print_r($_REQUEST, true);
        if (strlen($requestDump) < static::$requestSizeLimit) {
            $msg .= "\n\n_REQUEST:\n" . $requestDump;
        } else {
            $msg .= "\n\n_REQUEST: (excluded due to size)";
        }
        $msg .= "\n\n--- EOM ---\n";
        $subject =  $notification->getMessage() ." :: ". static::$projectName ." Notification";

        if (static::$defaultTransport === null) {
            $emails = implode(',', static::$devEmails);
            mail($emails, $subject, $msg);
            return;
        }

        $email = new Email();
        $email->setSubject($subject);
        $email->setTextBody($msg);
        if (static::$senderEmail !== null) {
            $email->setFrom(static::$senderEmail);
        } else {
            $email->setFrom(get_current_user() . '@' . gethostname());
        }
        $email->addRecipientsTo(static::$devEmails);

        static::$defaultTransport->send($email);
    }


    /**
     * Send a notification by the prefered channel
     *
     * @param Notification $notification
     */
    public static function any(Notification $notification): void
    {
        if (static::$loggingService !== null) {
            $msg = $notification->getMessage();
            if (is_object($notification->getException())) {
                $serviceEvent = new LoggingServiceEvent($notification->getException());
                if (! empty($msg)) {
                    $serviceEvent->setMessage($msg);
                }
            } else {
                $serviceEvent = new LoggingServiceEvent($msg);
            }
            $serviceEvent->setLevel($notification->getLevel());
            $serviceEvent->setContext($notification->getContext());
            if (($notification->getLogger() ?? "") !== "") {
                $serviceEvent->setLogger($notification->getLogger());
            }
            $success = static::$loggingService->send($serviceEvent);
            if ($success) {
                return;
            }
        }

        static::email($notification);
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

        if (is_object($e->getPrevious())) {
            $email .= "--- Previous Exception ---\n"
                . static::exceptionAsString($e->getPrevious());
        }

        return $email;
    }

}
