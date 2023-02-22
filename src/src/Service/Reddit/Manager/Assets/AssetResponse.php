<?php
declare(strict_types=1);

namespace App\Service\Reddit\Manager\Assets;

use App\Entity\Asset;
use Exception;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AssetResponse
{
    const MAX_RETRY_ATTEMPTS = 3;

    const ERROR_RESOLVE_HOST = 'Could not resolve host';

    const ERROR_SSL_CONNECT = 'OpenSSL SSL_connect: SSL_ERROR_SYSCALL';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Make an HTTP call to the provided Asset's source URL to retrieve its
     * media and header/mime type information.
     *
     * Due to intermittent issues in reaching Reddit's domain, retry logic
     * has been implemented as a workaround to retrieving the responses from
     * those error URLs that do return successfully on subsequent calls.
     *
     * @param  Asset  $asset
     * @param  int  $currentRetryNumber
     *
     * @return ResponseInterface|null
     * @throws TransportExceptionInterface
     */
    public function getAssetResponse(Asset $asset, int $currentRetryNumber = 0): ?ResponseInterface
    {
        try {
            $response = $this->httpClient->request('GET', $asset->getSourceUrl());
            if (200 !== $response->getStatusCode()) {
                if ($response->getStatusCode() === 404) {
                    return null;
                } else {
                    throw new Exception(sprintf(
                        'Unable to retrieve Asset from URL: %s. Status Code: %d. Response Info: %s',
                        $asset->getSourceUrl(),
                        $response->getStatusCode(),
                        var_export($response->getInfo(), true)
                    ));
                }
            }

            return $response;
        } catch (TransportException $e) {
            if ($this->isRedditIntermittentError($e) && $currentRetryNumber < self::MAX_RETRY_ATTEMPTS) {
                sleep(1);
                $currentRetryNumber += 1;
                return $this->getAssetResponse($asset, $currentRetryNumber);
            }

            throw $e;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Determine if the error message in the provided Exception matches any of
     * the expected errors that are thrown intermittently when attempting to
     * reach Reddit.
     *
     * @param  Exception  $e
     *
     * @return bool
     */
    private function isRedditIntermittentError(Exception $e): bool
    {
        $error = $e->getMessage();

        if (str_starts_with($error, self::ERROR_RESOLVE_HOST)
            || str_starts_with($error, self::ERROR_SSL_CONNECT)
        ) {
            return true;
        }

        return false;
    }
}
