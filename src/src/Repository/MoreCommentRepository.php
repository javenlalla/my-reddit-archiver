<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\MoreComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MoreComment>
 *
 * @method MoreComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method MoreComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method MoreComment[]    findAll()
 * @method MoreComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MoreCommentRepository extends ServiceEntityRepository
{
    const DEFAULT_LIMIT = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoreComment::class);
    }

    public function add(MoreComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MoreComment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all More Comment Entities under the provided More Comment's Parent
     * Comment.
     *
     * @param  MoreComment  $moreComment
     * @param  int  $limit
     *
     * @return array
     */
    public function findByRelatedParentComment(MoreComment $moreComment, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->createQueryBuilder('m')
           ->andWhere('m.parentComment = :parentComment')
           ->setParameter('parentComment', $moreComment->getParentComment());

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Find all More Comment Entities under the provided More Comment's Parent
     * Post.
     *
     * @param  MoreComment  $moreComment
     * @param  int  $limit
     *
     * @return array
     */
    public function findByRelatedParentPost(MoreComment $moreComment, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.parentPost = :parentPost')
            ->setParameter('parentPost', $moreComment->getParentPost());

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()
            ->getResult();
    }

//    /**
//     * @return MoreComment[] Returns an array of MoreComment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?MoreComment
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
