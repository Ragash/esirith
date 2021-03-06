<?php

namespace Game\BattleBundle\Manager;

use Game\BattleBundle\Entity\Battle;
use Game\BattleBundle\Model\BattleAttack;
use Game\BattleBundle\Model\BattleLog;
use Game\BattleBundle\Model\BattleResult;
use Game\CharacterBundle\Manager\XPManager;
use Game\CoreBundle\Manager\RollManager;
use Game\CharacterBundle\Entity\Character;
use Game\BattleBundle\Model\BattlePlayer;
use Game\CoreBundle\Model\Roll;
use Game\CharacterBundle\Manager\CharacterManager;
use Game\ItemBundle\Manager\ArmorManager;
use Game\MonsterBundle\Manager\MonsterManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Game\CharacterBundle\Event\CharacterEvent;

class BattleResolver
{


    /** @var Battle */
    protected $battle;

    /** @var BattleResult */
    protected $result;

    /** @var RollManager */
    protected $rollManager;

    /** @var Character */
    protected $character;

    /** @var CharacterManager */
    protected $characterManager;

    /** @var EventDispatcher */
    protected $eventDispatcher;

    /** @var XPManager */
    protected $XPManager;

    /** @var ArmorManager */
    protected $armorManager;

    /** @var MonsterManager */
    protected $monsterManager;

    public function __construct()
    {
        $this->result = new BattleResult();
    }

    /**
     * @param \Game\CharacterBundle\Manager\CharacterManager $characterManager
     */
    public function setCharacterManager($characterManager)
    {
        $this->characterManager = $characterManager;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param Battle $battle
     */
    public function setBattle(Battle $battle)
    {
        $this->battle = $battle;
        $this->character = $battle->getCharacter();
    }

    /**
     * @param RollManager $rollManager
     */
    public function setRollManager($rollManager)
    {
        $this->rollManager = $rollManager;
    }

    /**
     * @param \Game\CharacterBundle\Manager\XPManager $XPManager
     */
    public function setXPManager($XPManager)
    {
        $this->XPManager = $XPManager;
    }

    /**
     * @param \Game\ItemBundle\Manager\ArmorManager $armorManager
     */
    public function setArmorManager($armorManager)
    {
        $this->armorManager = $armorManager;
    }

    /**
     * @param MonsterManager $monsterManager
     */
    public function setMonsterManager($monsterManager)
    {
        $this->monsterManager = $monsterManager;
    }

    /**
     * @return BattleResult
     */
    public function getBattleResult()
    {
        return $this->result;
    }

    /**
     * Devuelve un array con los turnos de todos los Players (monstruos y jugadores)
     *
     * @return array
     */
    private function getPlayerTurns()
    {
        $init = array();

        $character = $this->character;
        $bp = new BattlePlayer();
        $bp->setPlayer($character);
        $bp->setInitiative($this->rollInitiative($character));
        $bp->setHp($character->getHp());
        $bp->setCurrentHp($character->getCurrentHp());
        $bp->setEnemy(false);
        $bp->setEquippedArmor($this->armorManager->getEquippedArmor($character));

        $init[] = $bp;

        $battleMonsters = $this->battle->getBattleMonsters();

        foreach ($battleMonsters as $battleMonster) {
            for ($i=0; $i<$battleMonster->getNumber(); $i++) {
                $bp = new BattlePlayer();
                $bp->setPlayer($battleMonster->getMonster());
                $bp->setInitiative($this->rollInitiative($bp->getPlayer()));
                $bp->setCurrentHp($battleMonster->getMonster()->getHp());
                $bp->setHp($battleMonster->getMonster()->getHp());
                $bp->setEnemy(true);
                $init[] = $bp;
            }
        }

        usort($init, array($this, "orderByInitiative"));

        return $init;
    }

    /**
     * Función comparativa: Ordena en orden descendente por iniciativas
     *
     * @param BattlePlayer $a
     * @param BattlePlayer $b
     * @return int
     */
    private static function orderByInitiative($a, $b)
    {
        $iniA = $a->getInitiative();
        $iniB = $b->getInitiative();

        if ($iniA == $iniB) {
            return 0;
        }
        return ($iniA < $iniB) ? +1 : -1;
    }

    /**
     * @param $player
     * @return mixed
     */
    private function rollInitiative($player)
    {
        return $this->rollManager->roll(1, 20)->getRollResult() + $player->getDex();
    }

    /**
     * Busca un objetivo para atacar
     * - random
     * - gamedo: hacer inteligente (al que tenga menos vida) o alguna cosa
     *
     * @param BattlePlayer $player
     * @param $order
     * @return BattlePlayer
     */
    private function findTarget(BattlePlayer $player, $order)
    {
        if (array_key_exists($player->getLastTarget(), $order)) {
            $lastTarget = $order[$player->getLastTarget()];
            if (!$lastTarget->isDead()) {
                return $player->getLastTarget();
            }
        }

        if ($player->getEnemy()) {
            $characterPos = $this->findCharacter($order);
            $character = $order[$characterPos];
            return $character->isDead() ? -1 : $characterPos;
        } else {
            $enemyPos = $this->findEnemy($order);
            if ($enemyPos === null) {
                return -1;
            }
            $enemy = $order[$enemyPos];
            return $enemy->isDead() ? -1 : $enemyPos;
        }
    }

    /**
     * Busca un character
     * gamedo: optimizar
     *
     * @param $order
     * @return int|string
     */
    private function findCharacter($order)
    {
        foreach ($order as $key => $val) {
            if (!$val->getEnemy()) {
                return $key;
            }
        }
    }

    /**
     * Busca un enemigo
     * gamedo: aportar algo de IA
     * gamedo: optimizar
     *
     * @param $order
     * @return mixed
     */
    private function findEnemy($order)
    {
        foreach ($order as $key => $val) {
            if ($val->getEnemy() && !$val->isDead()) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @param BattlePlayer $player
     * @param BattlePlayer $target
     * @return BattleAttack
     */
    private function attack(BattlePlayer &$player, BattlePlayer &$target)
    {
        if (!$player->getEnemy()) {
            $targetDefense = $target->getComputedDefense();
            $attack = $this->characterManager->rollAttack($player->getPlayer(), $targetDefense);
            //$target->decreaseHP($attack->getDamage());
            return $attack;
        } else {
            $attack = $this->monsterManager->attack($player, $target);
            return $attack;
        }
    }

    /**
     * Resuelve el combate
     */
    public function resolve()
    {
        $result = new BattleResult();
        $order = $this->getPlayerTurns();
        $finished = false;
        $turn = 1;
        $log = new BattleLog();
        $log->addTurn('The battle begins.');

        while ($finished === false) {
            $round = 1;
            foreach ($order as $player) {
                if ($player->isDead()) {
                    continue;
                }
                //1) a quien ataco
                $targetPos = $this->findTarget($player, $order);

                if ($targetPos>=0) {
                    $player->setLastTarget($targetPos);
                    /** @var BattlePlayer $target */
                    $target = $order[$targetPos];
                    //2) ataco
                    $log->addTurn($player->getPlayer()->getName().' attacks to '.$target->getPlayer()->getName().'...');
                    $attack = $this->attack($player, $target);
                    $target->decreaseHP($attack->getDamage());

                    if ($attack->getHits() > 0) {
                        if ($attack->getCriticals() > 0) {
                            $log->addTurn('Critical!');
                        }
                        $log->addTurn('Dealing '.$attack->getDamage().' points of damage');
                        $log->addTurn($target->getPlayer()->getName().' HP is now '.$target->getCurrentHp());
                    } else {
                        $log->addTurn('But misses');
                    }

                    //3) si esta muerto lo saco
                    if ($target->isDead()) {
                        //gana XP
                        $log->addTurn($target->getPlayer()->getName().' is dead.');
                        if ($target->getEnemy()) {
                            $result->addMonsterKilled($target);
                        }
                    }
                } else {
                    $log->addTurn('The battle ends.');
                    $finished = true;
                    $result->setStatus($player->getEnemy() ? Battle::STATUS_LOST : Battle::STATUS_WON);
                    break;
                }

                $round++;
            }
            $turn++;
        }
        //devuelvo la vida final del personaje
        $charPos = $this->findCharacter($order);
        $char = $order[$charPos];
        $result->setCurrentHP($char->getCurrentHp());
        $result->setLog($log);
        return $result;
    }
}
