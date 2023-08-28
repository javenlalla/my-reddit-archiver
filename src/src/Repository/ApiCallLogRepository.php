<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiCallLog;
use App\Model\GroupedApiCalls;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiCallLog>
 *
 * @method ApiCallLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiCallLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiCallLog[]    findAll()
 * @method ApiCallLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiCallLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiCallLog::class);
    }

    public function add(ApiCallLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApiCallLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find calls and group them into one minute intervals.
     *
     * @return GroupedApiCalls[]
     * @throws Exception
     */
    public function findCallsGroupedByMinute(): array
    {
        $sql = '
            SELECT
                DATE_FORMAT(created_at, \'%H:%i\') AS minuteCalled,
                count(a.id) AS totalCalls
            FROM api_call_log a
            GROUP BY
                UNIX_TIMESTAMP(a.created_at) DIV 60
            ORDER BY
                minuteCalled
        ';

        $q = $this->getEntityManager()->getConnection();
        $stmt = $q->prepare($sql);
        $result = $stmt->executeQuery();

        $allGroupedCalls = [];
        while($groupedCallsValues = $result->fetchAssociative()) {
            $groupedCalls = new GroupedApiCalls();
            $groupedCalls->minuteCalled = $groupedCallsValues['minuteCalled'];
            $groupedCalls->totalCalls = $groupedCallsValues['totalCalls'];

            $allGroupedCalls[] = $groupedCalls;
        }

        return $allGroupedCalls;
    }

//    /**
//     * @return ApiCallLog[] Returns an array of ApiCallLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ApiCallLog
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
