<?php

namespace Game\NotificationBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * NotificationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class NotificationRepository extends EntityRepository
{
    /**
     * @param $user
     * @param $game
     * @return array
     */
    public function getFromCharacter($game, $user)
    {
        $qb = $this->createQueryBuilder('notification');
        $qb
            ->join('notification.user', 'user')
            ->join('notification.game', 'game')
            ->where('user = :user')
            ->andWhere('game = :game')
            ->setParameters(array('user'=>$user, 'game'=>$game));

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param $user
     * @return array
     */
    public function getFromUser($user)
    {
        $qb = $this->createQueryBuilder('notification');
        $qb
            ->join('notification.user', 'user')
            ->where('user = :user')
            ->andWhere('notification.game IS NULL')
            ->setParameters(array('user'=>$user));

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param $game
     * @return array
     */
    public function getFromGame($game)
    {
        $qb = $this->createQueryBuilder('notification');
        $qb
            ->join('notification.game', 'game')
            ->where('notification.user IS NULL')
            ->andWhere('game = :game')
            ->setParameters(array('game'=>$game));

        return $qb->getQuery()->getArrayResult();
    }
}
