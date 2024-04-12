<?php

namespace App\Repository;

use App\Entity\SearchContent;
use App\Service\Search\Results;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
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
     * @return Results
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function search(?string $searchQuery, array $subreddits, array $flairTexts, array $tags, int $perPage, int $page): Results
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('c')
            ->addSelect('p')
            ->addSelect('pf')
            ->addSelect('cm')
            ->addSelect('cf')
            ->innerJoin('s.content', 'c')
            ->innerJoin('c.post', 'p')
            ->leftJoin('p.flairText', 'pf')
            ->leftJoin('c.comment', 'cm')
            ->leftJoin('cm.flairText', 'cf');

        if (!empty($searchQuery)) {
            $qb->andWhere('s.title LIKE :searchQuery OR s.contentText LIKE :searchQuery')
                ->setParameter('searchQuery', '%'.$searchQuery.'%');
        }

        if (!empty($subreddits)) {
            $qb->andWhere('s.subreddit IN (:subreddits)')
                ->setParameter('subreddits', $subreddits);
        }

        if (!empty($flairTexts)) {
            $qb->andWhere('s.flairText IN (:flairTexts)')
                ->setParameter('flairTexts', $flairTexts);
        }

        if (!empty($tags)) {
            $qb->andWhere(':tags MEMBER OF c.tags')
                ->setParameter('tags', $tags);
        }

        $searchTotalResults = $this->getSearchResultsCount($qb);

        $qb->orderBy('s.createdAt', 'DESC');
        $qb->setFirstResult($perPage * ($page - 1));
        $qb->setMaxResults($perPage);

        $searchQueryResults = $qb->getQuery()->getResult();

        $results = new Results();

        $results->setPerPage($perPage);
        $results->setPage($page);
        $results->setTotal($searchTotalResults);
        $results->setResults($searchQueryResults);

        return $results;
    }

    /**
     * Get the total count of results from the provided Search query.
     *
     * @param  QueryBuilder  $qb
     *
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getSearchResultsCount(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        $countQb->select('COUNT(c.id)');

        return $countQb->getQuery()
            ->getSingleScalarResult();
    }
}
