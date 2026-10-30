<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Nordkirche\Ndk\Api;
use Nordkirche\Ndk\Configuration;
use Nordkirche\NkcBase\Exception\ApiException;
use Nordkirche\NkcBase\Napi\Traffic\TrafficStats;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogManager;

/**
 * Creates and holds the single NDK instance of the installation.
 *
 * Inject this service (or call getApi()) instead of using the static
 * ApiService facade in new code.
 */
final class NdkApiFactory
{
    private ?Api $api = null;

    public function __construct(
        private readonly NapiSettings $settings,
        private readonly LogManager $logManager,
        private readonly TrafficStats $trafficStats,
    ) {}

    /**
     * @throws ApiException
     */
    public function getApi(): Api
    {
        return $this->api ??= $this->createApi();
    }

    /**
     * @throws ApiException
     */
    private function createApi(): Api
    {
        $missing = array_diff($this->settings->getMissing(), ['NDK_NAPI_USER_ID', 'NDK_NAPI_ACCESS_TOKEN']);
        if ($missing !== []) {
            throw new ApiException('Configuration error - please check API configuration (missing ' . implode(', ', $missing) . ')', 1495105254);
        }

        try {
            $configuration = new Configuration(
                (int)$this->settings->get('NDK_NAPI_USER_ID'),
                $this->settings->get('NDK_NAPI_ACCESS_TOKEN'),
                $this->settings->get('NDK_NAPI_HOST'),
                (int)$this->settings->get('NDK_NAPI_PORT'),
                $this->settings->get('NDK_NAPI_PATH'),
                $this->settings->get('NDK_NAPI_PROTOCOL'),
                (int)$this->settings->get('NDK_NAPI_VERSION'),
                $this->settings->getHttpAuth()
            );
            $configuration->setRequestTimeout($this->settings->getTimeout());

            $logFile = $this->settings->get('NDK_LOG_FILE');
            if ($logFile !== '' && Environment::getContext()->isDevelopment()) {
                // Full request log for debugging
                $logger = new Logger('NdkRequestLogger');
                $logger->pushHandler(new StreamHandler($logFile));
                $configuration->setLogger($logger);
            } else {
                $configuration->setLogger($this->logManager->getLogger('Nordkirche.Ndk'));
            }

            // Response times of all API requests for the status module (NDK 3.0+)
            if (method_exists($configuration, 'addMiddleware')) {
                $configuration->addMiddleware($this->trafficStats->createMiddleware($this->settings->getBaseUri()), 'nkcBaseTrafficStats');
            }

            return new Api($configuration);
        } catch (\Throwable $e) {
            throw new ApiException('Configuration error - please check API configuration', 1495105254, $e);
        }
    }
}
