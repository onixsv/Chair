<?php
declare(strict_types=1);

namespace Chair;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\player\Player;
use pocketmine\world\Position;

class Chair{

	/** @var AddActorPacket */
	protected AddActorPacket $addActorPacket;

	/** @var RemoveActorPacket */
	protected RemoveActorPacket $removeActorPacket;

	/** @var SetActorLinkPacket */
	protected SetActorLinkPacket $linkPacket;

	protected Position $pos;

	/** @var Player|null */
	protected ?Player $player = null;

	protected int $id;

	public function __construct(Position $pos){
		$this->pos = $pos;
		$this->id = Entity::nextRuntimeId();

		$addActorPacket = new AddActorPacket();
		$addActorPacket->actorUniqueId = $addActorPacket->actorRuntimeId = $this->id;
		$flags = 1 << EntityMetadataFlags::INVISIBLE;
		$flags ^= 1 << EntityMetadataFlags::NO_AI;
		$flags ^= 1 << EntityMetadataFlags::CAN_SHOW_NAMETAG;
		$flags ^= 1 << EntityMetadataFlags::ALWAYS_SHOW_NAMETAG;
		$addActorPacket->metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty($flags)
		];
		$addActorPacket->position = $this->pos->floor()->add(0.5, 1.8, 0.5); // make as center
		$addActorPacket->type = EntityIds::ZOMBIE;
		$this->addActorPacket = $addActorPacket;

		$removeActorPacket = new RemoveActorPacket();
		$removeActorPacket->actorUniqueId = $this->id;
		$this->removeActorPacket = $removeActorPacket;

		$linkPacket = new SetActorLinkPacket();
		$linkPacket->link = new EntityLink($this->id, -1, EntityLink::TYPE_RIDER, true, true);
		$this->linkPacket = $linkPacket;
	}

	public function sitOn(Player $player) : void{
		if($this->equals($player)){
			return;
		}
		if($this->hasPlayer()){
			$player->sendPopup("이 §d의자§f에는 §d" . $this->player->getName() . "§f님이 앉아있습니다.");
			return;
		}
		//$player->getLevel()->broadcastPacketToViewers($player, $this->addActorPacket);
		$player->getNetworkSession()->sendDataPacket(clone $this->addActorPacket);
		$this->linkPacket->link->toActorUniqueId = $player->getId();
		$this->linkPacket->link->type = EntityLink::TYPE_RIDER;
		//$player->getLevel()->broadcastPacketToViewers($player, $this->linkPacket);
		$player->getNetworkSession()->sendDataPacket(clone $this->linkPacket);
		$player->getServer()->broadcastPackets($player->getViewers(), [clone $this->linkPacket]);
		$player->sendPopup("의자에서 내려오려면 §d점프§f버튼을 클릭해주세요.");
		$this->player = $player;
	}

	public function standUp(Player $player) : void{
		if(!$this->hasPlayer()){
			return; // why??
		}
		if(!$this->equals($player)){
			return; // why too??
		}
		$this->linkPacket->link->toActorUniqueId = $player->getId();
		$this->linkPacket->link->type = EntityLink::TYPE_REMOVE;
		$player->getNetworkSession()->sendDataPacket(clone $this->linkPacket);
		$player->getServer()->broadcastPackets($player->getViewers(), [clone $this->linkPacket]);
		$player->getNetworkSession()->sendDataPacket(clone $this->removeActorPacket);
		$this->player = null;
	}

	public function equals(Player $that) : bool{
		if(!$this->player instanceof Player){
			return false;
		}
		return $this->player->getName() === $that->getName();
	}

	public function hasPlayer() : bool{
		return $this->player instanceof Player;
	}
}