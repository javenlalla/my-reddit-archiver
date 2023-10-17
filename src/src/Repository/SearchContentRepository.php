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

    /**
     * Perform a Search against the Search Contents by constructing and
     * executing a query based on the provided search parameters.
     *
     * @param  string|null  $searchQuery
     * @param  array  $subreddits
     * @param  array  $flairTexts
     * @param  array  $tags
     * @param  int  $perPage
     * @param  int  $page
     *
     * @return array
     */
    public function search(?string $searchQuery, array $subreddits, array $flairTexts, array $tags, int $perPage, int $page): array
    {
        $qb = $this->createQueryBuilder('s');

        if (!empty($searchQuery)) {
            $qb->andWhere('s.title LIKE :searchQuery OR s.contentText LIKE :searchQuery')
                ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }

        $qb->setFirstResult($perPage * ($page - 1));
        $qb->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
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
