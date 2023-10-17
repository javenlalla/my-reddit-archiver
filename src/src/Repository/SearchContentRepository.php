<?php

namespace App\Repository;

use App\Entity\SearchContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchContent>
 *
 * @method SearchContent|null find($id, $lockMode = null, $lockVersion = null)
 * @method SearchContent|null findOneBy(array $criteria, array $orderBy = null)
 * @method SearchContent[]    findAll()
 * @method SearchContent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchContent::class);
    }

//    /**
//     * @return SearchContent[] Returns an array of SearchContent objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SearchContent
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
