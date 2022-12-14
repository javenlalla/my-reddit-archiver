<?php

namespace App\Repository;

use App\Entity\ApiUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiUser>
 *
 * @method ApiUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiUser[]    findAll()
 * @method ApiUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiUser::class);
    }

    public function add(ApiUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApiUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ApiUser[] Returns an array of ApiUser objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    public function findOneByUsername(string $username): ?ApiUser
    {
       return $this->createQueryBuilder('a')
           ->andWhere('a.username = :username')
           ->setParameter('username', $username)
           ->getQuery()
           ->getOneOrNullResult()
       ;
    }

    /**
     * Retrieve an API Access Token for the provided Username as currently
     * stored in the database.
     *
     * @param  string  $username
     *
     * @return string
     */
    public function getAccessTokenByUsername(string $username): string
    {
        $accessToken = $this->findOneByUsername($username)?->getAccessToken();

        if (!empty($accessToken)) {
            return $accessToken;
        }

        return '';
    }

    public function saveToken(string $username, string $accessToken)
    {
        $apiUser = $this->findOneByUsername($username);
        $apiUser->setAccessToken($accessToken);

        $this->getEntityManager()->persist($apiUser);
        $this->getEntityManager()->flush();
    }
}
