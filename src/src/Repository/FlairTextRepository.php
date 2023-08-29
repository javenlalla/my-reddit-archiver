<?php

namespace App\Repository;

use App\Entity\FlairText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlairText>
 *
 * @method FlairText|null find($id, $lockMode = null, $lockVersion = null)
 * @method FlairText|null findOneBy(array $criteria, array $orderBy = null)
 * @method FlairText[]    findAll()
 * @method FlairText[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FlairTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlairText::class);
    }

//    /**
//     * @return FlairText[] Returns an array of FlairText objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?FlairText
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
