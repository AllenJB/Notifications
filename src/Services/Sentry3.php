<?php

namespace AllenJB\Notifications\Services;

use AllenJB\Notifications\LoggingServiceEvent;
use AllenJB\Notifications\LoggingServiceInterface;
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

        // Ensure the LoggingServiceEvent class is loaded - this should help prevent logging from failing in cases
        // where available memory might be low
        new LoggingServiceEvent("preloading");
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


    /**
     * @param LoggingServiceEvent $event
     * @param bool $includeSessionData Include session specific data ($_SESSION, $_REQUEST) - useful to exclude when parsing error logs
     * @return null|string Event ID (if successful)
     */
    public function send(LoggingServiceEvent $event, $includeSessionData = true) : bool
    {
        if ($this->client === null) {
            return false;
        }

        $sentryEvent = Event::createEvent();

        // user is not null or empty array (we know user is either array or null)
        if ($this->user !== null) {
            $sentryEvent->setUser($this->user);
        }
        if (! empty($event->getUser())) {
            $userDataBag = UserDataBag::createFromArray($event->getUser());
            $sentryEvent->setUser($userDataBag);
        }

        if ($event->getTimeStamp() !== null) {
            $sentryEvent->setTimestamp($event->getTimeStamp()->getTimestamp());
        }
        if (($event->getFingerprint() ?? "") !== "") {
            $sentryEvent->setFingerprint(['{{default}}', $event->getFingerprint()]);
        }

        $level = Severity::info();
        if ($event->getLevel() !== null) {
            $levelStr = $event->getLevel();
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

        if ($event->getLogger() !== null) {
            $sentryEvent->setLogger($event->getLogger());
        }
        $sentryEvent->setTags($event->getTags());

        foreach ($event->getContext() as $key => $value) {
            $sentryEvent->setContext($key, $value);
        }
        $sentryEvent->setContext('_SERVER', $_SERVER);
        if ($includeSessionData) {
            $sentryEvent->setContext('_REQUEST', $_REQUEST);
            if (isset($_SESSION)) {
                $sentryEvent->setContext('_SESSION', $_SESSION);
            }
        }

        if ($event->getException() !== null) {
            $sentryEvent->setExceptions([new ExceptionDataBag($event->getException())]);
            $this->client->getOptions()->setAttachStacktrace(false);
        } elseif (! $event->getExcludeStackTrace()) {
            $this->client->getOptions()->setAttachStacktrace(false);
        }

        $sentryEvent->setMessage($event->getMessage());
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
