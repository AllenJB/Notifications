<?php

namespace AllenJB\Notifications\Services;

use AllenJB\Notifications\LoggingServiceInterface;
use AllenJB\Notifications\Notification;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;

class Sentry2 implements LoggingServiceInterface
{

    protected static $instance = null;

    protected ClientInterface $client;

    protected array $user;

    protected string $appEnvironment;

    protected ?string $appVersion;

    protected ?string $publicDSN = null;


    /**
     * @return static|null
     */
    public static function getInstance() : ?Sentry2
    {
        return static::$instance;
    }


    public static function setInstance(Sentry2 $instance) : void
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
        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use ($globalTags) : void {
            $scope->setTags($globalTags);
        });
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


    public function send(Notification $notification, $includeSessionData = true) : bool
    {
        if ($this->client === null) {
            return false;
        }

        $lastEventId = null;
        SentrySdk::getCurrentHub()->withScope(function (Scope $scope) use ($notification, &$lastEventId) : void {
            // user is not null or empty array (we know user is either array or null)
            if (! empty($this->user)) {
                $scope->setUser($this->user);
            }

            $data = [];
            if ($notification->getTimeStamp() !== null) {
                $dt = new \DateTimeImmutable($notification->getTimeStamp()->format('c'));
                $dt = $dt->setTimezone(new \DateTimeZone("UTC"));
                $data['timestamp'] = $dt->format('Y-m-d\TH:i:s\Z');
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
                $scope->setLevel($level);
            }
            if ($notification->getLogger() !== null) {
                $data['logger'] = $notification->getLogger();
            }
            if (! empty($notification->getContext())) {
                $scope->setExtras($notification->getContext());
            }
            $scope->setExtra('_SERVER', $_SERVER);
            if ($notification->shouldIncludeSessionData()) {
                $scope->setExtra('_REQUEST', $_REQUEST);
                if (isset($_SESSION)) {
                    $scope->setExtra('_SESSION', $_SESSION);
                }
            }

            if ($notification->getException() !== null) {
                $data['exception'] = $notification->getException();
                $this->client->getOptions()->setAttachStacktrace(false);
            } elseif (! $notification->shouldExcludeStackTrace()) {
                $this->client->getOptions()->setAttachStacktrace(false);
            }

            $data["message"] = $notification->getMessage();
            $data["level"] = $level;
            $lastEventId = $this->client->captureEvent($data, null, $scope);

            $this->client->getOptions()->setAttachStacktrace(true);
        });

        return ($lastEventId !== null);
    }


    public function generateBrowserJS() : string
    {
        if (($this->publicDSN ?? "") === "") {
            return "";
        }

        $loggingService = Sentry2::getInstance();
        $user = null;
        if ($loggingService !== null) {
            $user = $loggingService->getUser();
        }

        $retval = "
        <script
          src=\"https://browser.sentry-cdn.com/5.15.4/bundle.min.js\"
          integrity=\"sha384-Nrg+xiw+qRl3grVrxJtWazjeZmUwoSt0FAVsbthlJ5OMpx0G08bqIq3b/v0hPjhB\"
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
