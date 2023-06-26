<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemJson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemJson>
 *
 * @method ItemJson|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemJson|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemJson[]    findAll()
 * @method ItemJson[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemJsonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemJson::class);
    }

    public function add(ItemJson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ItemJson $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retrieve an Item Json Entity by its Reddit ID.
     *
     * @param  string  $redditId
     *
     * @return ItemJson|null
     * @throws NonUniqueResultException
     */
    public function findByRedditId(string $redditId): ?ItemJson
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.redditId = :redditId')
            ->setParameter('redditId', $redditId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Retrieve all Item Json Entities by the provided array of Reddit IDs.
     *
     * @param  array  $redditIds
     *
     * @return ItemJson[]
     */
    public function findByRedditIds(array $redditIds): array
    {
       return $this->createQueryBuilder('i')
           ->andWhere('i.redditId IN (:redditIds)')
           ->setParameter('redditIds', $redditIds)
           ->getQuery()
           ->getResult()
       ;
    }

//    /**
//     * @return ItemJson[] Returns an array of ItemJson objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ItemJson
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
