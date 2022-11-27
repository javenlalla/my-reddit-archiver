<?php
declare(strict_types=1);


namespace App\Controller\API;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

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
     *
     * @return JsonResponse
     */
    #[Route('/healthcheck', name: 'healthcheck')]
    public function healthcheck(EntityManagerInterface $em, CacheInterface $cachePool)
    {
        $healthChecks = [
            'reddit-credentials-set' => false,
            'database-connected' => false,
            'cache-connected' => false,
            'error' => null,
        ];

        try {
            $healthChecks['reddit-credentials-set'] = $this->verifyRedditCredentialsSet();
            $healthChecks['database-connected'] = $this->verifyDatabaseConnection($em);
            $healthChecks['cache-connected'] = $this->verifyCacheConnection($cachePool);
        } catch (InvalidArgumentException | Exception $e) {
            $healthChecks['error'] = $e->getMessage();
        }

        return $this->json($healthChecks);
    }

    /**
     * Verify the required Reddit parameters to power access to Reddit's API
     * and user data are set.
     *
     * @return bool
     * @throws Exception
     */
    private function verifyRedditCredentialsSet(): bool
    {
        foreach (self::REDDIT_CREDENTIAL_PARAMS as $paramName) {
            $paramValue = $this->getParameter($paramName);
            if (empty($paramValue)) {
                throw new Exception(sprintf('Reddit Credential parameter `%s` missing or empty.', $paramName));
            }
        }

        return true;
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
}
