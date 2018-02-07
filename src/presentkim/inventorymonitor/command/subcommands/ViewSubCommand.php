<?php

namespace presentkim\inventorymonitor\command\subcommands;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\CommandSender;
use presentkim\inventorymonitor\InventoryMonitor as Plugin;
use presentkim\inventorymonitor\command\{
  PoolCommand, SubCommand
};
use presentkim\inventorymonitor\inventory\SyncInventory;
use presentkim\inventorymonitor\util\{
  Translation, Utils
};

class ViewSubCommand extends SubCommand{

    public function __construct(PoolCommand $owner){
        parent::__construct($owner, 'view');
    }

    /**
     * @param CommandSender $sender
     * @param String[]      $args
     *
     * @return bool
     */
    public function onCommand(CommandSender $sender, array $args) : bool{
        if ($sender instanceof Player) {
            if (isset($args[0])) {
                $server = Server::getInstance();
                $playerName = strtolower($args[0]);
                $nbt = null;
                $player = $server->getPlayerExact($playerName);
                if ($player === null) {
                    if (file_exists("{$server->getDataPath()}players\\{$playerName}.dat")) {
                        $nbt = $server->getOfflinePlayerData($playerName);
                    } else {
                        $sender->sendMessage(Plugin::$prefix . Translation::translate('command-generic-failure@invalid-player', $args[0]));
                        return true;
                    }
                }
                if (!isset(SyncInventory::$instances[$playerName])) {
                    SyncInventory::$instances[$playerName] = new SyncInventory($playerName, $nbt);
                }
                $sender->addWindow(SyncInventory::$instances[$playerName]);
            } else {
                return false;
            }
        } else {
            $sender->sendMessage(Plugin::$prefix . Translation::translate('command-generic-failure@in-game'));
        }
        return true;
    }
}