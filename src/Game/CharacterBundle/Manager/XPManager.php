<?php

namespace Game\CharacterBundle\Manager;

use Doctrine\ORM\EntityManager;
use Game\CharacterBundle\Entity\Character;
use Game\MonsterBundle\Entity\Monster;
use Game\CharacterBundle\Manager\CharacterManager;

class XPManager
{
    const LEVELUP_XP_NEEDED = 100;

    const LEVELUP_STAT_HEALTH = 1;
    const LEVELUP_STAT_DAMAGE = 2;
    const LEVELUP_STAT_DEFENSE = 3;
    const LEVELUP_STAT_STR = 4;
    const LEVELUP_STAT_DEX = 5;

    /** @var CharacterManager */
    protected $characterManager;

    /**
     * @param \Game\CharacterBundle\Manager\CharacterManager $characterManager
     */
    public function setCharacterManager($characterManager)
    {
        $this->characterManager = $characterManager;
    }

    public function flush()
    {
        $this->characterManager->flush();
    }

    /**
     * @param Character $char
     * @param Monster $monster
     * @return int
     */
    public function calcKillXP(Character $char, Monster $monster)
    {
        return rand(2,5);
    }

    /**
     * @param Character $char
     * @return bool
     */
    public function isReadyToLevelUp(Character $char)
    {
        return ($char->getXp() >= self::LEVELUP_XP_NEEDED);
    }

    /**
     * @param Character $char
     * @param $stat
     */
    public function levelUp(Character $char, $stat)
    {
        switch ($stat)
        {
            case self::LEVELUP_STAT_HEALTH:
                $char->setHp($char->getHp() + 10);
                break;
            case self::LEVELUP_STAT_DAMAGE:
                $char->setDamage($char->getDamage() + 1);
                break;
            case self::LEVELUP_STAT_DEFENSE:
                $char->setDefense($char->getDefense() + 1);
                break;
            case self::LEVELUP_STAT_STR:
                $char->setStr($char->getStr() + 1);
                break;
            case self::LEVELUP_STAT_DEX:
                $char->setDex($char->getDex() + 1);
                break;
        }
        $char->setXP($char->getXP() - self::LEVELUP_XP_NEEDED);
        $this->characterManager->persist($char);
    }
}