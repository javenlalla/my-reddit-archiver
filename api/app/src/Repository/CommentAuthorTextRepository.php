<?php

namespace App\Repository;

use App\Entity\CommentAuthorText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentAuthorText>
 *
 * @method CommentAuthorText|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentAuthorText|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentAuthorText[]    findAll()
 * @method CommentAuthorText[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentAuthorTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentAuthorText::class);
    }

    public function add(CommentAuthorText $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommentAuthorText $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return CommentAuthorText[] Returns an array of CommentAuthorText objects
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

//    public function findOneBySomeField($value): ?CommentAuthorText
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
