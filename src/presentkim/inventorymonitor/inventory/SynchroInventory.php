<?php

namespace presentkim\inventorymonitor\inventory;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\inventory\{
  BaseInventory, CustomInventory
};
use pocketmine\math\Vector3;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\{
  CompoundTag, IntTag, StringTag
};
use pocketmine\network\mcpe\protocol\{
  types\WindowTypes, UpdateBlockPacket, ContainerOpenPacket, BlockEntityDataPacket
};
use pocketmine\tile\Spawnable;
use presentkim\inventorymonitor\util\Translation;

class SynchroInventory extends CustomInventory{

    /** @var NetworkLittleEndianNBTStream|null */
    private static $nbtWriter = null;

    /** @var  self[] */
    public static $synchros = [];

    /** CompoundTag */
    private $nbt;

    /** Vector3[] */
    private $vectors = [];

    /**
     * SynchroInventory constructor.
     *
     * @param string      $playerName
     * @param CompoundTag $namedTag
     */
    public function __construct(string $playerName, ?CompoundTag $namedTag = null){
        $items = [];
        $player = Server::getInstance()->getPlayerExact($playerName);
        if ($player instanceof Player) {
            $inventory = $player->getInventory();
            for ($i = 0, $size = $inventory->getSize(); $i < $size; ++$i) {
                $item = $inventory->getItem($i);
                if (!$item->isNull()) {
                    $items[$i] = $item;
                }
            }
        } elseif ($namedTag !== null) {
            $inventoryTag = $namedTag->getListTag("Inventory");
            if ($inventoryTag !== null) {
                /** @var CompoundTag $item */
                foreach ($inventoryTag as $i => $itemTag) {
                    $slot = $itemTag->getByte("Slot");
                    if ($slot > 9 && $slot < 100) {
                        $items[$slot - 9] = Item::nbtDeserialize($itemTag);
                    }
                }
            }
        }
        parent::__construct(new Vector3(0, 0, 0), $items, 54, null);

        $this->nbt = new CompoundTag('', [
          new StringTag('id', 'Chest'),
          new IntTag('x', 0),
          new IntTag('y', 0),
          new IntTag('z', 0),
          new StringTag('CustomName', Translation::translate('chest-name', $player instanceof Player ? $player->getName() : $playerName)),
        ]);

        if (self::$nbtWriter === null) {
            self::$nbtWriter = new NetworkLittleEndianNBTStream();
        }
    }

    /** @param Player $who */
    public function onOpen(Player $who) : void{
        BaseInventory::onOpen($who);

        $this->vectors[$key = $who->getLowerCaseName()] = $who->subtract(0, 3, 0)->floor();
        if ($this->vectors[$key]->y < 0) {
            $this->vectors[$key]->y = 0;
        }
        for ($i = 0; $i < 2; $i++) {
            $pk = new UpdateBlockPacket();
            $pk->blockId = Block::CHEST;
            $pk->blockData = 0;
            $pk->x = $this->vectors[$key]->x + $i;
            $pk->y = $this->vectors[$key]->y;
            $pk->z = $this->vectors[$key]->z;
            $who->sendDataPacket($pk);


            $this->nbt->setInt('x', $this->vectors[$key]->x + $i);
            $this->nbt->setInt('y', $this->vectors[$key]->y);
            $this->nbt->setInt('z', $this->vectors[$key]->z);
            $this->nbt->setInt('pairx', $this->vectors[$key]->x + (1 - $i));
            $this->nbt->setInt('pairz', $this->vectors[$key]->z);
            self::$nbtWriter->setData($this->nbt);

            $pk = new BlockEntityDataPacket();
            $pk->x = $this->vectors[$key]->x + $i;
            $pk->y = $this->vectors[$key]->y;
            $pk->z = $this->vectors[$key]->z;
            $pk->namedtag = self::$nbtWriter->write();
            $who->sendDataPacket($pk);
        }

        $pk = new ContainerOpenPacket();
        $pk->type = WindowTypes::CONTAINER;
        $pk->entityUniqueId = -1;
        $pk->x = $this->vectors[$key]->x;
        $pk->y = $this->vectors[$key]->y;
        $pk->z = $this->vectors[$key]->z;
        $pk->windowId = $who->getWindowId($this);
        $who->sendDataPacket($pk);

        $this->sendContents($who);
    }

    public function onClose(Player $who) : void{
        BaseInventory::onClose($who);
        $key = $who->getLowerCaseName();
        for ($i = 0; $i < 2; $i++) {
            $block = $who->getLevel()->getBlock($vec = $this->vectors[$key]->add($i, 0, 0));

            $pk = new UpdateBlockPacket();
            $pk->x = $vec->x;
            $pk->y = $vec->y;
            $pk->z = $vec->z;
            $pk->blockId = $block->getId();
            $pk->blockData = $block->getDamage();
            $who->sendDataPacket($pk);

            $tile = $who->getLevel()->getTile($vec);
            if ($tile instanceof Spawnable) {
                $who->sendDataPacket($tile->createSpawnPacket());
            }
        }
        unset($this->vectors[$key]);
    }

    /** @return string */
    public function getName() : string{
        return "SynchroInventory";
    }

    /** @return int */
    public function getDefaultSize() : int{
        return 54;
    }

    /** @return int */
    public function getNetworkType() : int{
        return WindowTypes::CONTAINER;
    }
}