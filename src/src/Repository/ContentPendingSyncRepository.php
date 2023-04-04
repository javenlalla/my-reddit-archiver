<?php

namespace App\Repository;

use App\Entity\ContentPendingSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentPendingSync>
 *
 * @method ContentPendingSync|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContentPendingSync|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContentPendingSync[]    findAll()
 * @method ContentPendingSync[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContentPendingSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentPendingSync::class);
    }

    public function add(ContentPendingSync $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContentPendingSync $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find any Content Pending Syncs by the provided array of Reddit IDs.
     *
    * @return ContentPendingSync[] Returns an array of ContentPendingSync objects
    */
    public function findPendingSyncsByRedditIds(array $redditIds = []): array
    {
       return $this->createQueryBuilder('c')
           ->andWhere('c.fullRedditId IN (:redditIds)')
           ->setParameter('redditIds', $redditIds)
           ->getQuery()
           ->getResult()
       ;
    }

//    /**
//     * @return ContentPendingSync[] Returns an array of ContentPendingSync objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ContentPendingSync
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
