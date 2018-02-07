<?php

namespace presentkim\inventorymonitor\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use presentkim\inventorymonitor\InventoryMonitor as Plugin;
use presentkim\inventorymonitor\inventory\SyncInventory;

class PlayerEventListener implements Listener{

    /** @var Plugin */
    private $owner = null;

    public function __construct(){
        $this->owner = Plugin::getInstance();
    }

    /**
     * @priority LOWEST
     *
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoinEvent(PlayerJoinEvent $event){
        if (!$event->isCancelled()) {
            $player = $event->getPlayer();
            if (isset(SyncInventory::$instances[$playerName = $player->getLowerCaseName()])) {
                $inventory = $player->getInventory();
                $syncInventory = SyncInventory::$instances[$playerName];
                for ($i = 0, $size = $inventory->getSize(); $i < $size; ++$i) {
                    $inventory->setItem($i, $syncInventory->getItem($i));
                }
            }
        }
    }
}