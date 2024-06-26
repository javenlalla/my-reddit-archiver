<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 *
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Create the Criteria needed for retrieving the latest/current version of
     * a Comment's Comment Author Text entity.
     *
     * @return Criteria
     */
    public static function createLatestCommentAuthorTextCriteria(): Criteria
    {
        return Criteria::create()
            ->orderBy(['createdAt' => Order::Descending])
            ->setMaxResults(1)
            ;
    }

    public function add(Comment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Comment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Persist the provided array of Comments to the database.
     *
     * @param  Comment[]  $comments
     *
     * @return void
     */
    public function saveComments(array $comments)
    {
        foreach ($comments as $comment) {
            $this->getEntityManager()->persist($comment);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Get the total number of Comments, including Replies, attached to the
     * provided Post.
     *
     * @param  Post  $post
     *
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getTotalPostCount(Post $post): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->andWhere('c.parentPost = :val')
            ->setParameter('val', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Fetch all Comments associated to the provided Post.
     *
     * Optionally, sort and return top-level Comments only.
     *
     * @param  Post  $post
     * @param  bool  $topLevelCommentsOnly
     *
     * @return Comment[]
     */
    public function getOrderedCommentsByPost(Post $post, bool $topLevelCommentsOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.parentPost = :parentPost')
            ->setParameter('parentPost', $post)
            ->orderBy('c.score', 'DESC')
        ;

        if ($topLevelCommentsOnly === true) {
            $qb->andWhere('c.parentComment IS NULL');
        }

        return $qb
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Comment[] Returns an array of Comment objects
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

//    public function findOneBySomeField($value): ?Comment
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
