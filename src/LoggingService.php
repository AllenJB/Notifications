<?php

namespace AllenJB\Notifications;

use Sentry\ClientBuilder;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

class LoggingService
{

    protected static $instance = null;

    protected $client;

    protected $user;

    protected $appEnvironment;

    protected $appVersion;

    protected $publicDSN = null;


    /**
     * @return static|null
     */
    public static function getInstance() : ?LoggingService
    {
        return static::$instance;
    }


    public static function setInstance(LoggingService $instance) : void
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

        Hub::getCurrent()->configureScope(function (Scope $scope) use ($globalTags) : void {
            $scope->setTags($globalTags);
        });

        // Ensure the LoggingServiceEvent class is loaded - this should help prevent logging from failing in cases
        // where available memory might be low
        new LoggingServiceEvent("preloading");
    }


    public function setUser(array $user = null) : void
    {
        if (is_array($user)) {
            foreach ($user as $key => $value) {
                if (! is_scalar($value)) {
                    throw new \InvalidArgumentException("User data array may only contain scalar values and may not contain arrays ({$key})");
                }
            }

            if (empty($user)) {
                $user = null;
            }
        }

        $this->user = $user;
    }


    public function getUser() : ?array
    {
        return $this->user;
    }


    /**
     * @param LoggingServiceEvent $event
     * @param bool $includeSessionData Include session specific data ($_SESSION, $_REQUEST) - useful to exclude when parsing error logs
     * @return null|string Event ID (if successful)
     */
    public function send(LoggingServiceEvent $event, $includeSessionData = true) : ?string
    {
        if ($this->client === null) {
            return null;
        }

        $lastEventId = null;
        Hub::getCurrent()->withScope(function (Scope $scope) use ($event, $includeSessionData, &$lastEventId) : void {
            // user is not null or empty array (we know user is either array or null)
            if (! empty($this->user)) {
                $scope->setUser($this->user);
            }
            if (! empty($event->getUser())) {
                $scope->setUser($event->getUser());
            }

            $data = [];
            if ($event->getTimeStamp() !== null) {
                $dt = new \DateTimeImmutable($event->getTimeStamp()->format('c'));
                $dt = $dt->setTimezone(new \DateTimeZone("UTC"));
                $data['timestamp'] = $dt->format('Y-m-d\TH:i:s\Z');
            }
            if (($event->getFingerprint() ?? "") !== null) {
                $data['fingerprint'] = ['{{default}}', $event->getFingerprint()];
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
                $scope->setLevel($level);
            }
            if ($event->getLogger() !== null) {
                $data['logger'] = $event->getLogger();
            }
            $tags = $event->getTags();
            if (! empty($tags)) {
                foreach ($tags as $key => $value) {
                    $scope->setTag($key, $value);
                }
            }
            if (! empty($event->getContext())) {
                $scope->setExtras($event->getContext());
            }
            $scope->setExtra('_SERVER', $_SERVER);
            if ($includeSessionData) {
                $scope->setExtra('_REQUEST', $_REQUEST);
                if (isset($_SESSION)) {
                    $scope->setExtra('_SESSION', $_SESSION);
                }
            }

            if ($event->getException() !== null) {
                $data['exception'] = $event->getException();
                $this->client->getOptions()->setAttachStacktrace(false);
            } elseif (! $event->getExcludeStackTrace()) {
                $this->client->getOptions()->setAttachStacktrace(false);
            }

            $data["message"] = $event->getMessage();
            $data["level"] = $level;
            try {
                $lastEventId = $this->client->captureEvent($data, $scope);
            } catch (\Exception $e) {
                Notifications::email($e->getMessage(), "Error Logging Service Error", $e);
            }

            $this->client->getOptions()->setAttachStacktrace(true);
        });

        return $lastEventId;
    }


    public function generateBrowserJS() : string
    {
        if (($this->publicDSN ?? "") === "") {
            return "";
        }

        $loggingService = LoggingService::getInstance();
        $user = null;
        if ($loggingService !== null) {
            $user = $loggingService->getUser();
        }

        $retval = "
        <script
          src=\"https://browser.sentry-cdn.com/5.11.0/bundle.min.js\"
          integrity=\"sha384-jbFinqIbKkHNg+QL+yxB4VrBC0EAPTuaLGeRT0T+NfEV89YC6u1bKxHLwoo+/xxY\"
          crossorigin=\"anonymous\"></script>
        <script type=\"text/javascript\">
            Sentry.init({
                dsn: " . $this->jsonEncode($this->publicDSN) . ",
                release: " . $this->jsonEncode($this->appVersion) . ",
                environment: " . $this->jsonEncode($this->appEnvironment) . ",
                sever_name: " . $this->jsonEncode(gethostname()) . ",
            });";

        if ($user !== null) {
            $retval .= "
                Sentry.setUser({
                    email: " . $this->jsonEncode($user["email"] ?? "") . ",
                    id: " . $this->jsonEncode($user["id"] ?? "") . ",
                    username: " . $this->jsonEncode($user["username"] ?? "") . ",
                });";
        }

        $retval .= "
        </script>
        ";

        return $retval;
    }


    protected function jsonEncode(
        $value,
        $options = JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS
    ) : string {
        $retval = json_encode($value, $options);

        if ($retval === false) {
            $msg = "Failed to encode as JSON";
            if (function_exists('json_last_error_msg')) {
                $msg .= ': ' . json_last_error_msg();
            }

            throw new \InvalidArgumentException($msg, json_last_error());
        }

        return $retval;
    }

}
