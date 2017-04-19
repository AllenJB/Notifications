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

        if (is_object($exception) && ($exception instanceof \Exception)) {
            $msg .= "\n\n" . ErrorHandler::exceptionAsString($exception);
        } else {
            $msg .= "\n\nBacktrace:\n" . ErrorHandler::stackTraceString();
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

}
