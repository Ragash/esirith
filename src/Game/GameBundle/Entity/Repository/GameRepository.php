<?php

namespace Game\GameBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Game\GameBundle\Entity\Game;

/**
 * GameRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class GameRepository extends EntityRepository
{

    /**
     * @return array
     */
    public function getInProgressWithBoss()
    {
        $qb = $this->createQueryBuilder('game');

        $qb
            ->select('game, boss')
            ->join('game.boss', 'boss')
            ->where('game.status = :status')
            ->setParameters(array('status' => Game::STATUS_IN_PROGRESS));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array
     */
    public function getAvailableGamesWithPlayers()
    {
        $qb = $this->createQueryBuilder('game');

        $qb
            ->select('game, char')
            ->leftJoin('game.characters', 'char')
            ->where('game.status = :status')
            ->setParameters(array('status' => Game::STATUS_IN_PROGRESS));
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param $charId
     * @return array
     */
    public function getFromCharacter($charId)
    {
        $qb = $this->createQueryBuilder('game');

        $qb
            ->select('game.id, game.name')
            ->leftJoin('game.characters', 'char')
            ->where('char.id = :charid')
            ->setParameters(array('charid' => $charId));
        return $qb->getQuery()->getSingleResult(Query::HYDRATE_ARRAY);
    }
}
