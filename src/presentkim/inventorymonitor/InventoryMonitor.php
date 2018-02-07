<?php

namespace presentkim\inventorymonitor;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ListTag;
use pocketmine\plugin\PluginBase;
use presentkim\inventorymonitor\command\PoolCommand;
use presentkim\inventorymonitor\command\subcommands\{
  ViewSubCommand, LangSubCommand, ReloadSubCommand
};
use presentkim\inventorymonitor\inventory\SyncInventory;
use presentkim\inventorymonitor\listener\{
  InventoryEventListener, PlayerEventListener
};
use presentkim\inventorymonitor\util\Translation;

class InventoryMonitor extends PluginBase{

    /** @var self */
    private static $instance = null;

    /** @var string */
    public static $prefix = '';

    /** @return self */
    public static function getInstance() : self{
        return self::$instance;
    }

    /** @var PoolCommand */
    private $command;

    public function onLoad() : void{
        if (self::$instance === null) {
            self::$instance = $this;
            Translation::loadFromResource($this->getResource('lang/eng.yml'), true);
        }
    }

    public function onEnable() : void{
        $this->load();
        $this->getServer()->getPluginManager()->registerEvents(new InventoryEventListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener(), $this);
    }

    public function onDisable() : void{
        foreach (SyncInventory::$instances as $playerName => $syncInventory) {
            $player = $this->getServer()->getPlayerExact($playerName);
            if ($player !== null) {
                $inventory = $player->getInventory();
                for ($i = 0; $i < 36; ++$i) {
                    $inventory->setItem($i, $syncInventory->getItem($i));
                }
            } else {
                $namedTag = $this->getServer()->getOfflinePlayerData($playerName);
                $inventoryTag = new ListTag("Inventory", [], NBT::TAG_Compound);
                for ($i = 0; $i < 36; ++$i) {
                    $item = $syncInventory->getItem($i);
                    if (!$item->isNull()) {
                        $inventoryTag[$i + 9] = $item->nbtSerialize($i + 9);
                    }
                }
                $namedTag->setTag($inventoryTag);
                $this->getServer()->saveOfflinePlayerData($playerName, $namedTag);
            }
            foreach ($syncInventory->getViewers() as $key => $who) {
                $syncInventory->close($who);
            }
        }
        SyncInventory::$instances = [];
    }

    public function load() : void{
        $dataFolder = $this->getDataFolder();
        if (!file_exists($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }

        $langfilename = $dataFolder . 'lang.yml';
        if (!file_exists($langfilename)) {
            $resource = $this->getResource('lang/eng.yml');
            fwrite($fp = fopen("{$dataFolder}lang.yml", "wb"), $contents = stream_get_contents($resource));
            fclose($fp);
            Translation::loadFromContents($contents);
        } else {
            Translation::load($langfilename);
        }

        self::$prefix = Translation::translate('prefix');
        $this->reloadCommand();
    }

    public function reloadCommand() : void{
        if ($this->command == null) {
            $this->command = new PoolCommand($this, 'inventorymonitor');
            $this->command->createSubCommand(ViewSubCommand::class);
            $this->command->createSubCommand(LangSubCommand::class);
            $this->command->createSubCommand(ReloadSubCommand::class);
        }
        $this->command->updateTranslation();
        $this->command->updateSudCommandTranslation();
        if ($this->command->isRegistered()) {
            $this->getServer()->getCommandMap()->unregister($this->command);
        }
        $this->getServer()->getCommandMap()->register(strtolower($this->getName()), $this->command);
    }

    /**
     * @param string $name = ''
     *
     * @return PoolCommand
     */
    public function getCommand(string $name = '') : PoolCommand{
        return $this->command;
    }

    /** @param PoolCommand $command */
    public function setCommand(PoolCommand $command) : void{
        $this->command = $command;
    }
}
