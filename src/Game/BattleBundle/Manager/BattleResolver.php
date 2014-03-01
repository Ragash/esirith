<?php

namespace Game\BattleBundle\Manager;

use Game\BattleBundle\Entity\Battle;
use Game\BattleBundle\Model\BattleAttack;
use Game\BattleBundle\Model\BattleResult;
use Game\CoreBundle\Manager\RollManager;
use Game\CharacterBundle\Entity\Character;
use Game\BattleBundle\Model\BattlePlayer;
use Game\CoreBundle\Model\Roll;
use Game\CharacterBundle\Manager\CharacterManager;

class BattleResolver {

    const DEBUG = true;

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

    function __construct()
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
     * @param $a
     * @param $b
     * @return int
     */
    static function orderByInitiative($a, $b)
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
        foreach ($order as $key=>$val) {
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
        foreach ($order as $key=>$val) {
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
            $attack = $this->characterManager->rollAttack($player->getPlayer(), $target->getPlayer()->getDefense());
            //$target->decreaseHP($attack->getDamage());
            return $attack;
        } else {
            //$target->decreaseHp(1);
            $attack = new BattleAttack();
            $attack->setDamage(1);
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

        while ($finished === false) {
            $round = 1;
            foreach ($order as $player) {
                $this->log("[".$player->getPlayer()->getName()."]");
                if ($player->isDead()) {
                    $this->log("dead");
                    $this->log("---------------------");
                    continue;
                }
                $this->log("Turno: $turn, Ronda: $round, [Ini:".$player->getInitiative()."]");
                //1) a quien ataco
                $targetPos = $this->findTarget($player, $order);
                //$this->log("TargetPos: $targetPos");
                if ($targetPos>=0) {
                    $player->setLastTarget($targetPos);
                    /** @var BattlePlayer $target */
                    $target = $order[$targetPos];
                    $this->log("Target: ".$target->getPlayer()->getName());
                    //2) ataco
                    $attack = $this->attack($player, $target);
                    $target->decreaseHP($attack->getDamage());
                    $this->log("Attack dmg:".$attack->getDamage().", crit:".$attack->getCriticals().", hits:".$attack->getHits().", miss:".$attack->getMiss());
                    $this->log("Target HP: ".$target->getCurrentHp()."/".$target->getHp());

                    //3) si esta muerto lo saco
                    if ($target->isDead()) {
                        $this->log("Lo mata");
                        //unset($order[$targetPos]);
                    }
                } else {
                    $this->log("No hay objetivo, gana.");
                    $finished = true;
                    $result->setStatus($player->getEnemy() ? Battle::STATUS_LOST : Battle::STATUS_WON);
                    break;
                }
                $this->log("---------------------");

                $round++;
            }
            $turn++;
        }
        //devuelvo la vida final del personaje
        $charPos = $this->findCharacter($order);
        $char = $order[$charPos];
        $result->setCurrentHP($char->getCurrentHp());
        return $result;
    }

    public function log($str)
    {
        if (self::DEBUG) {
            echo $str."<br />";
        }
    }
}