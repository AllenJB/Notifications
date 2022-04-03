<?php

namespace AllenJB\Notifications\Services;

use AllenJB\Notifications\LoggingServiceInterface;
use AllenJB\Notifications\Notification;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\UserDataBag;

class Sentry3 implements LoggingServiceInterface
{

    protected static $instance = null;

    protected ClientInterface $client;

    protected ?UserDataBag $user = null;

    protected string $appEnvironment;

    protected ?string $appVersion;

    protected ?string $publicDSN = null;

    protected array $globalTags;


    /**
     * @return static|null
     */
    public static function getInstance() : ?Sentry3
    {
        return static::$instance;
    }


    public static function setInstance(Sentry3 $instance) : void
    {
        static::$instance = $instance;
    }


    public function __construct(
        string $sentryDSN,
        string $appEnvironment,
        ?string $appVersion,
        array $globalTags,
        ?string $publicDSN = null
    ) {
        $this->appEnvironment = $appEnvironment;
        $this->appVersion = $appVersion;
        $this->publicDSN = $publicDSN;

        $globalTags['sapi'] = PHP_SAPI;

        $sentryOptions = [
            'release' => $appVersion,
            'environment' => $appEnvironment,
            'dsn' => $sentryDSN,
            'max_value_length' => 4096,
            'send_default_pii' => true,
            'attach_stacktrace' => true,
        ];
        $this->client = ClientBuilder::create($sentryOptions)->getClient();

        foreach ($globalTags as $key => $value) {
            if ($key === "") {
                throw new \InvalidArgumentException("Tag key cannot be an empty string");
            }
            if ($value === "") {
                throw new \InvalidArgumentException("Tag value cannot be an empty string");
            }
        }
        $this->globalTags = $globalTags;
        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use ($globalTags) : void {
            $scope->setTags($globalTags);
        });
    }


    public function setUser(array $user = null) : void
    {
        if (empty($user)) {
            $this->user = null;
            return;
        }

        foreach ($user as $key => $value) {
            if (! is_scalar($value)) {
                throw new \InvalidArgumentException("User data array may only contain scalar values and may not contain arrays ({$key})");
            }
        }

        $this->user = UserDataBag::createFromArray($user);
    }


    public function send(Notification $notification) : bool
    {
        if ($this->client === null) {
            return false;
        }

        $sentryEvent = Event::createEvent();

        // user is not null or empty array (we know user is either array or null)
        if ($this->user !== null) {
            $sentryEvent->setUser($this->user);
        }

        if ($notification->getTimeStamp() !== null) {
            $sentryEvent->setTimestamp($notification->getTimeStamp()->getTimestamp());
        }

        $level = Severity::info();
        if ($notification->getLevel() !== null) {
            $levelStr = $notification->getLevel();
            switch ($levelStr) {
                case 'debug':
                    $level = Severity::debug();
                    break;

                case 'info':
                    $level = Severity::info();
                    break;

                case 'warning':
                    $level = Severity::warning();
                    break;

                case 'error':
                    $level = Severity::error();
                    break;

                case 'fatal':
                    $level = Severity::fatal();
                    break;

                default:
                    trigger_error("Unhandled event level: " . $levelStr, E_USER_WARNING);
                    break;
            }
        }
        $sentryEvent->setLevel($level);

        if ($notification->getLogger() !== null) {
            $sentryEvent->setLogger($notification->getLogger());
        }
        $sentryEvent->setTags($this->globalTags);

        foreach ($notification->getContext() as $key => $value) {
            $sentryEvent->setContext($key, $value);
        }
        $sentryEvent->setContext('_SERVER', $_SERVER);
        if ($notification->shouldIncludeSessionData()) {
            $sentryEvent->setContext('_REQUEST', $_REQUEST);
            if (isset($_SESSION)) {
                $sentryEvent->setContext('_SESSION', $_SESSION);
            }
        }

        if ($notification->getException() !== null) {
            $sentryEvent->setExceptions([new ExceptionDataBag($notification->getException())]);
            $this->client->getOptions()->setAttachStacktrace(false);
        } elseif (! $notification->shouldExcludeStackTrace()) {
            $this->client->getOptions()->setAttachStacktrace(false);
        }

        $sentryEvent->setMessage($notification->getMessage());
        $lastEventId = $this->client->captureEvent($sentryEvent, null, null);

        $this->client->getOptions()->setAttachStacktrace(true);

        return ($lastEventId !== null);
    }


    public function getBrowseJSConfig() : \stdClass
    {
        $retval = (object) [
            "release" => $this->appVersion,
            "environment" => $this->appEnvironment,
            "initialScope" => (object) [
                "tags" => $this->globalTags,
            ],
            "server_name" => gethostname(),
        ];
        if ($this->publicDSN !== null) {
            $retval->dsn = $this->publicDSN;
        }
        if ($this->user !== null) {
            $retval->initialScope->user = (object) [
                "id" => $this->user->getId(),
                "email" => $this->user->getEmail(),
                "username" => $this->user->getUsername(),
            ];
        }
        return $retval;
    }

}
