<?php

namespace presentkim\inventorymonitor\task;

use pocketmine\Player;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\scheduler\Task;

class SendDataPacketTask extends Task{

    /** @var Player */
    private $player;

    /** @var DataPacket[] */
    private $packets;

    /**
     * SendDataPacketTask constructor.
     *
     * @param Player       $player
     * @param DataPacket[] $packets
     */
    public function __construct(Player $player, DataPacket ...$packets){
        $this->player = $player;
        $this->packets = $packets;
    }

    /** @param int $currentTick */
    public function onRun(int $currentTick) : void{
        foreach ($this->packets as $key => $packet) {
            $this->player->sendDataPacket($packet);
        }
    }
}