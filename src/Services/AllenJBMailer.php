<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Services;

use AllenJB\Mailer\Email;
use AllenJB\Mailer\Transport\AbstractTransport;
use AllenJB\Notifications\ErrorHandler;
use AllenJB\Notifications\LoggingServiceInterface;
use AllenJB\Notifications\Notification;
use AllenJB\Notifications\Notifications;

class AllenJBMailer implements LoggingServiceInterface
{

    protected AbstractTransport $transport;

    /**
     * @var array<string>
     */
    protected array $recipientEmails;

    protected string $fromEmail;

    protected ?string $fromName = null;

    protected string $subjectSuffix = "";

    protected int $requestSizeMaxBytes = 2048;


    public function __construct(AbstractTransport $mailTransport, array $recipientEmails)
    {
        $this->transport = $mailTransport;
        $this->recipientEmails = $recipientEmails;
        $this->fromEmail = get_current_user() . '@' . gethostname();
    }


    public function setFromName(string $fromDisplayName): void
    {
        $this->fromName = $fromDisplayName;
    }


    public function setFromEmail(string $email): void
    {
        $this->fromEmail = $email;
    }


    public function setSubjectSuffix(string $suffix): void
    {
        $this->subjectSuffix = $suffix;
    }


    public function setRequestSizeMaxBytes(int $maxBytes): void
    {
        $this->requestSizeMaxBytes = $maxBytes;
    }


    public function send(Notification $notification): bool
    {
        $msg = "Automated Notification:\n" . ($notification->getMessage() ?? "");
        $subject = $notification->getMessage();

        $exception = $notification->getException();
        if ($exception !== null) {
            $subject = $exception->getMessage();
            if ($notification->getMessage() !== null) {
                $msg .= "\n";
            }
            $msg .= $exception->getMessage();
        }
        if (strlen($subject ?? '') > 120) {
            $subject = substr($subject, 0, 120) .'...';
        }

        foreach ($notification->getContext() as $key => $value) {
            $msg .= "\n\n{$key}: " . var_export($value, true);
        }

        if ($exception !== null) {
            $msg .= "\n\n" . Notifications::exceptionAsString($exception);
        } else {
            $msg .= "\n\nBacktrace:\n" . ErrorHandler::stackTraceString();
        }

        $msg .= "\n\n_SERVER: " . print_r($_SERVER, true);

        if ($notification->shouldIncludeSessionData()) {
            $requestDump = print_r($_REQUEST, true);
            if (strlen($requestDump) < $this->requestSizeMaxBytes) {
                $msg .= "\n\n_REQUEST:\n" . $requestDump;
            } else {
                $msg .= "\n\n_REQUEST: (excluded due to size)";
            }
        }
        $msg .= "\n\n--- EOM ---\n";
        $subject = "". ($subject ?? "Notification") . $this->subjectSuffix;

        $email = new Email();
        $email->setSubject($subject);
        $email->setTextBody($msg);
        $email->setFrom($this->fromEmail, $this->fromName);
        $email->addRecipientsTo($this->recipientEmails);

        return $this->transport->send($email);
    }

}
