<?php
ini_set('display_errors', 'stderr');

use App\Kernel;
use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\PSR7Client;
use Spiral\RoadRunner\Worker;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\StreamFactory;
use Zend\Diactoros\UploadedFileFactory;
use Symfony\Component\HttpFoundation\Cookie;

require __DIR__ . '/../vendor/autoload.php';

// The check is to ensure we don't use .env in production
if (!isset($_SERVER['APP_ENV']) && !isset($_ENV['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new \RuntimeException(
            'APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.'
        );
    }
    (new Dotenv())->load(__DIR__ . '/../.env');
}

$env   = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ('prod' !== $env));

if ($debug) {
    umask(0000);

    Debug::enable();
}
if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

$kernel = new Kernel($env, $debug);
$relay  = new StreamRelay(STDIN, STDOUT);
$psr7   = new PSR7Client(new Worker($relay));

$httpFoundationFactory = new HttpFoundationFactory();
$psrHttpFactory        = new PsrHttpFactory(
    new ServerRequestFactory,
    new StreamFactory,
    new UploadedFileFactory,
    new ResponseFactory
);


while ($req = $psr7->acceptRequest()) {
    try {
        $request   = $httpFoundationFactory->createRequest($req);
        $sessionId = (string) $request->cookies->get(session_name());
        unset($_SESSION);

        // Set new session id for PHP or forget previous request session id.
        \session_id($sessionId);
        $response = $kernel->handle($request);

        if (\session_id() !== $sessionId) {
            // Set session cookie if session id was changed
            $response->headers->setCookie(
                Cookie::create(
                    \session_name(),
                    \session_id()
                )
            );
        }

        $psr7->respond($psrHttpFactory->createResponse($response));
        $kernel->terminate($request, $response);
        $kernel->reboot(null);
    } catch (\Throwable $exception) {
        $psr7->getWorker()->error('Internal server error.');

        $date   = new \DateTime();
        $handle = \fopen(__DIR__ . '/../var/log/worker.log', 'a');
        \fwrite($handle, "{$date->format('m/d/Y H:i:s')} [message]: {$exception->getMessage()} {$exception->getFile()}:{$exception->getLine()}" . PHP_EOL);
    }
}
