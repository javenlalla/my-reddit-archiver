<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProfileContentGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileContentGroup>
 *
 * @method ProfileContentGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProfileContentGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProfileContentGroup[]    findAll()
 * @method ProfileContentGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfileContentGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileContentGroup::class);
    }

    public function add(ProfileContentGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProfileContentGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find and return a Profile Content Group by the provided Group Name.
     *
     * @param  string  $groupName
     *
     * @return ProfileContentGroup
     */
    public function getGroupByName(string $groupName): ProfileContentGroup
    {
        return $this->findOneBy(['groupName' => $groupName]);
    }

//    /**
//     * @return ProfileContentGroup[] Returns an array of ProfileContentGroup objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProfileContentGroup
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
