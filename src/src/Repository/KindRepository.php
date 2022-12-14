<?php

namespace App\Repository;

use App\Entity\Kind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kind>
 *
 * @method Kind|null find($id, $lockMode = null, $lockVersion = null)
 * @method Kind|null findOneBy(array $criteria, array $orderBy = null)
 * @method Kind[]    findAll()
 * @method Kind[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KindRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kind::class);
    }

    public function add(Kind $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Kind $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Kind
     */
    public function getLinkType(): Kind
    {
        return $this->findOneBy(['redditKindId' => Kind::KIND_LINK]);
    }

    /**
     * @return Kind
     */
    public function getCommentType(): Kind
    {
        return $this->findOneBy(['redditKindId' => Kind::KIND_COMMENT]);
    }

//    /**
//     * @return Kind[] Returns an array of Kind objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('k')
//            ->andWhere('k.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('k.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Kind
//    {
//        return $this->createQueryBuilder('k')
//            ->andWhere('k.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
