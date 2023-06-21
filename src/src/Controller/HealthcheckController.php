<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Reddit\Api;
use App\Service\Typesense\Api as TypesenseApi;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\TypesenseClientError;

class HealthcheckController extends AbstractController
{
    private const REDDIT_CREDENTIAL_PARAMS = [
        'app.reddit.username',
        'app.reddit.password',
        'app.reddit.client_id',
        'app.reddit.client_secret',
    ];

    /**
     * Perform a series of health checks for the application and return the
     * result of each check.
     *
     * @param  EntityManagerInterface  $em
     * @param  CacheInterface  $cachePool
     * @param  TypesenseApi  $typesenseApi
     * @param  Api  $redditApi
     * @param  RateLimiterFactory  $redditApiLimiter
     *
     * @return JsonResponse
     */
    #[Route('/healthcheck', name: 'healthcheck')]
    public function healthcheck(
        EntityManagerInterface $em,
        CacheInterface $cachePool,
        TypesenseApi $typesenseApi,
        Api $redditApi,
        RateLimiterFactory $redditApiLimiter,
    ) {
        $healthChecks = [
            'database-connected' => false,
            'cache-connected' => false,
            'typesense-connected' => false,
            'reddit-credentials-set' => false,
            'error' => null,
            'rate-limit' => [
                'calls-remaining' => 0,
                'retry-datetime' => '',
                'retry-timestamp' => 0,
                'limit' => 0,
            ]
        ];

        try {
            $healthChecks['database-connected'] = $this->verifyDatabaseConnection($em);
            $healthChecks['cache-connected'] = $this->verifyCacheConnection($cachePool);
            $healthChecks['typesense-connected'] = $this->verifyTypesenseConnection($typesenseApi);
            $healthChecks['reddit-credentials-set'] = $this->verifyRedditCredentialsSet($redditApi);
            $healthChecks['rate-limit'] = $this->verifyRateLimit($redditApiLimiter);
        } catch (InvalidArgumentException | Exception $e) {
            $healthChecks['error'] = $e->getMessage();
        }

        return $this->json($healthChecks);
    }

    /**
     * Verify the database is reachable.
     *
     * @param  EntityManagerInterface  $em
     *
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    private function verifyDatabaseConnection(EntityManagerInterface $em): bool
    {
        $em->getConnection()->connect();
        if ($em->getConnection()->isConnected()) {
            return true;
        }

        return false;
    }

    /**
     * Verify the cache pool is reachable.
     *
     * @param  CacheInterface  $cachePool
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    private function verifyCacheConnection(CacheInterface $cachePool): bool
    {
        $cacheKey = 'healthCheckKey';
        $testValue = $cachePool->get($cacheKey, function() {
            return 'cacheValueSet';
        });

        $cachePool->delete($cacheKey);

        return true;
    }

    /**
     * Verify the required Reddit parameters to power access to Reddit's API
     * and user data are set.
     *
     * @param  Api  $redditApi
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    private function verifyRedditCredentialsSet(Api $redditApi): bool
    {
        foreach (self::REDDIT_CREDENTIAL_PARAMS as $paramName) {
            $paramValue = $this->getParameter($paramName);
            if (empty($paramValue)) {
                throw new Exception(sprintf('Reddit Credential parameter `%s` missing or empty.', $paramName));
            }
        }

        $context = new Api\Context('HealthcheckController:verifyRedditCredentialsSet');
        $savedPosts = $redditApi->getSavedContents($context, 1);
        if (isset($savedPosts['children'])) {
            return true;
        }

        throw new Exception(sprintf('Unexpected response from Reddit API with configured account credentials: %s', var_export($savedPosts, true)));
    }

    /**
     * Verify the Typesense server can be reached and returns the Contents
     * Collection.
     *
     * @param  TypesenseApi  $typesenseApi
     *
     * @return bool
     * @throws \Http\Client\Exception
     * @throws ConfigError
     * @throws TypesenseClientError
     */
    private function verifyTypesenseConnection(TypesenseApi $typesenseApi): bool
    {
        $contentsCollection = $typesenseApi->getContentsCollection();
        if (!empty($contentsCollection)) {
            return true;
        }

        return false;
    }

    /**
     * Verify the status of the current rate limit to the Reddit API.
     *
     * @param  RateLimiterFactory  $redditApiLimiter
     *
     * @return array
     */
    private function verifyRateLimit(RateLimiterFactory $redditApiLimiter): array
    {
        $redditUsername = $this->getParameter(self::REDDIT_CREDENTIAL_PARAMS[0]);
        $limiter = $redditApiLimiter->create($redditUsername);

        // Explicitly provide 0 as to not affect the current rate limit status.
        $limit = $limiter->consume(0);

        $retryDateTime = '';
        $retryTimestamp = 0;

        if ($limit->getRemainingTokens() <= 0) {
            $retryDateTimeObj = $limit->getRetryAfter()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            $retryDateTime = $retryDateTimeObj->format('Y-m-d H:i:s');
            $retryTimestamp= $retryDateTimeObj->getTimestamp();
        }

        return [
            'calls-remaining' => $limit->getRemainingTokens(),
            'retry-datetime' => $retryDateTime,
            'retry-timestamp' => $retryTimestamp,
            'limit' => $limit->getLimit(),
        ];
    }
}
