<?php

namespace App\Repository;

use App\Entity\Type;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Type>
 *
 * @method Type|null find($id, $lockMode = null, $lockVersion = null)
 * @method Type|null findOneBy(array $criteria, array $orderBy = null)
 * @method Type[]    findAll()
 * @method Type[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Type::class);
    }

    public function add(Type $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Type $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Type
     */
    public function getImageType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_IMAGE]);
    }

    /**
     * @return Type
     */
    public function getVideoType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_VIDEO]);
    }

    /**
     * @return Type
     */
    public function getTextType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_TEXT]);
    }

    /**
     * @return Type
     */
    public function getImageGalleryType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_IMAGE_GALLERY]);
    }

    /**
     * @return Type
     */
    public function getGifType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_GIF]);
    }

    /**
     * @return Type
     */
    public function getExternalLinkType(): Type
    {
        return $this->findOneBy(['name' => Type::CONTENT_TYPE_EXTERNAL_LINK]);
    }

//    /**
//     * @return Type[] Returns an array of Type objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Type
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
