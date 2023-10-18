<?php

namespace App\Repository;

use App\Entity\Content;
use App\Entity\SearchContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
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
     * Delete a Search Content Entity.
     *
     * @param  SearchContent  $entity
     * @param  bool  $flush
     *
     * @return void
     */
    public function remove(SearchContent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
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
     * @return Content[]
     */
    public function search(?string $searchQuery, array $subreddits, array $flairTexts, array $tags, int $perPage, int $page): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('c')
            ->innerJoin(Content::class, 'c', Join::WITH, 's.content = c.id')
        ;

        if (!empty($searchQuery)) {
            $qb->andWhere('s.title LIKE :searchQuery OR s.contentText LIKE :searchQuery')
                ->setParameter('searchQuery', '%' . $searchQuery . '%');
        }

        if (!empty($subreddits)) {
            $qb->andWhere('s.subreddit IN (:subreddits)')
                ->setParameter('subreddits', $subreddits);
        }

        $qb->orderBy('s.createdAt', 'DESC');
        $qb->setFirstResult($perPage * ($page - 1));
        $qb->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
    }
}
