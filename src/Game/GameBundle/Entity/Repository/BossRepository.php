<?php

namespace Game\GameBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Game\GameBundle\Entity\Boss;
use Game\MapBundle\Entity\Poi;

/**
 * BossRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BossRepository extends EntityRepository
{
    /**
     * @param Poi $poi
     * @return Boss
     */
    public function getBossFromPoi(Poi $poi)
    {
        $qb = $this->createQueryBuilder('boss');

        $qb
            ->select('boss')
            ->where('boss.currentPoi = :poi')
            ->setParameters(array('poi' => $poi));

        return $qb->getQuery()->getOneOrNullResult();
    }
}
