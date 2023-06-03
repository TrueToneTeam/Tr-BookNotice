<?php

namespace TrueToneTeam\BookNotice;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\VanillaItems as Items;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class BookNotice extends PluginBase implements Listener{

	private array $pageData = [];

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		foreach($this->getResources() as $resource){
			$this->saveResource($resource->getFilename());
		}

		$loadNoticeTextFileCount = 0;
		foreach(scandir($this->getDataFolder()) as $fileName){
			if($fileName != "." && $fileName != ".." && (substr($fileName, 0, 5) == "page_")){
				++$loadNoticeTextFileCount;
				$this->pageData[$loadNoticeTextFileCount] = str_replace("\r", "", file_get_contents($this->getDataFolder() . $fileName));
			}
		}
		$this->getServer()->getLogger()->notice(match($this->getServer()->getLanguage()->getLang()){
			"kor" => TextFormat::GOLD.$loadNoticeTextFileCount."개".TextFormat::RESET."의 파일이 인식되었습니다.",
			default => TextFormat::GOLD.$loadNoticeTextFileCount.TextFormat::RESET." files were recognized."
		});
	}

	public function onJoin(PlayerJoinEvent $event){
		$this->openToBook($event->getPlayer());
	}


	private function openToBook(Player $player) : void{
		$playerInventory = $player->getInventory();
		$originSlot = $playerInventory->getHeldItemIndex();
		$originItem = clone $playerInventory->getItemInHand();

		$book = clone Items::WRITTEN_BOOK();
		$book->addPage((count($this->pageData) - 1));

		$bookPage = 0;
		foreach($this->pageData as $text){
			if($book->pageExists($bookPage)){
				$book->setPageText($bookPage, $text);
				++$bookPage;
			}
		}

		$playerInventory->setItemInHand($book);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($player, $originSlot, $book) : void{
			$player->getNetworkSession()->sendDataPacket(InventoryTransactionPacket::create(
				0,
				[],
				UseItemTransactionData::new(
					[],
					UseItemTransactionData::ACTION_CLICK_AIR,
					new BlockPosition(0, 0, 0),
					255,
					$originSlot,
					ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($book)),
					$player->getPosition(),
					new Vector3(0, 0, 0),
					0
				)
			));
		}), 3);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($playerInventory, $originItem) : void{
			$playerInventory->setItemInHand($originItem);
		}), 3);
			
	}
}