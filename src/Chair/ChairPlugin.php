<?php
declare(strict_types=1);

namespace Chair;

use MySetting\MySetting;
use MySetting\Setting;
use pocketmine\block\Stair;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\Position;

class ChairPlugin extends PluginBase implements Listener{

	/** @var Chair[] */
	protected $chairs = [];

	/** @var float[] */
	protected $taps = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @handleCancelled true
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if($block instanceof Stair){
			if($block->getMeta() >= 4 && $block->getMeta() <= 7){
				return; // 밑으로 세워진 의자
			}
			$chair = $this->getChair($block->getPosition());
			if(($setting = MySetting::getInstance()->getSetting($player)) instanceof Setting){
				if($setting->getSetting(Setting::SETTINGS_SIT_CHAIR)){
					if(!isset($this->taps[$player->getName()])){
						$this->taps[$player->getName()] = microtime(true);
						$player->sendPopup("의자에 §d앉으려면 §f한번 더 §d터치§f해주세요.");
					}elseif(isset($this->taps[$player->getName()])){
						if(microtime(true) - $this->taps[$player->getName()] <= 0.5){
							$chair->sitOn($player);
						}else{
							$this->taps[$player->getName()] = microtime(true);
							$player->sendPopup("의자에 §d앉으려면 §f한번 더 §d터치§f해주세요.");
						}
					}
				}else{
					$player->sendPopup("§d의자§f에 앉으려면 §d설정§f에서 §d의자앉기§f를 활성화 해주세요.");
				}
			}
		}
	}

	public function getChairFor(Player $player) : ?Chair{
		foreach(array_values($this->chairs) as $chair){
			if($chair->equals($player)){
				return $chair;
			}
		}
		return null;
	}

	public function getChair(Position $pos) : Chair{
		$p = implode(":", [$pos->x, $pos->y, $pos->z, $pos->world->getFolderName()]);
		if(isset($this->chairs[$p])){
			unset($this->chairs[$p]);
		}
		return $this->chairs[$p] = new Chair($pos);
	}

	public function onDataPacketReceived(DataPacketReceiveEvent $event) : void{
		$player = $event->getOrigin()->getPlayer();
		$packet = $event->getPacket();
		if(!$player instanceof Player){
			return;
		}
		if($packet instanceof InteractPacket){
			if($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE){
				if(($chair = $this->getChairFor($player)) instanceof Chair){
					$chair->standUp($player);
				}
			}
		}elseif($packet instanceof PlayerActionPacket){
			if($packet->action === PlayerAction::JUMP){
				if(($chair = $this->getChairFor($player)) instanceof Chair){
					$chair->standUp($player);
				}
			}
		}
	}
}