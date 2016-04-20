<?php

/*
 * MagicWE
 * WorldEdit for PocketMine
 *
 * https://github.com/thebigsmileXD/MagicWE
 *
 * Made by @thebigsmileXD / @XenialDan and @svilex!
 * Thanks so much to @svilex for creating most of the schematics import + export code! (https://github.com/svilex/Schematic_Loader)
 * You are so awesome, dude! Couldn't have got this done without you!
 *
 * https://github.com/thebigsmileXD
 * https://github.com/svilex
 */
namespace MagicWE;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener{
	public $areas;
	private $pos1 = [], $pos2 = [], $copy = [], $copypos = [], $undo = [], $undometa = [], $undopos = [], $wand = [], $schematics = [];
	private static $MAX_BUILD_HEIGHT = 128;

	public function onLoad(){
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE has been loaded!");
	}

	public function onEnable(){
		$this->saveResource("config.yml");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE enabled!");
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($sender instanceof Player){
			switch($command){
				case "/pos1":
					{
						if(!$sender->hasPermission("magicwe.command.pos1")) return;
						$pos1x = $sender->getFloorX();
						$pos1y = $sender->getFloorY();
						$pos1z = $sender->getFloorZ();
						$this->pos1[$sender->getName()] = new Vector3($pos1x, $pos1y, $pos1z);
						if($pos1y > self::$MAX_BUILD_HEIGHT || $pos1y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
						$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 1 set as x:" . $pos1x . " y:" . $pos1y . " z:" . $pos1z);
						return true;
						break;
					}
				
				case "/pos2":
					{
						if(!$sender->hasPermission("magicwe.command.pos2")) return;
						$pos2x = $sender->getFloorX();
						$pos2y = $sender->getFloorY();
						$pos2z = $sender->getFloorZ();
						$this->pos2[$sender->getName()] = new Vector3($pos2x, $pos2y, $pos2z);
						if($pos2y > self::$MAX_BUILD_HEIGHT || $pos2y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
						$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 2 set as x:" . $pos2x . " y:" . $pos2y . " z:" . $pos2z);
						return true;
						break;
					}
				
				case "/set":
					{
						if(!$sender->hasPermission("magicwe.command.set")) return;
						if(isset($args[0])){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$this->fill($sender, $args[0]);
								return true;
							}
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
						}
						break;
					}
				
				case "/replace":
					{
						if(!$sender->hasPermission("magicwe.command.replace")) return;
						if(isset($args[0]) && isset($args[1])){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$this->replace($sender, $args[0], $args[1]);
								return true;
							}
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
						}
						break;
					}
				
				case "/copy":
					{
						if(!$sender->hasPermission("magicwe.command.copy")) return;
						if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
							$this->copy($sender);
							return true;
						}
						break;
					}
				
				case "/paste":
					{
						if(!$sender->hasPermission("magicwe.command.paste")) return;
						if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
							$this->paste($sender);
							return true;
						}
						break;
					}
				
				case "/undo":
					{
						if(!$sender->hasPermission("magicwe.command.undo")) return;
						if(isset($this->undopos[$sender->getName()])){
							$this->undo($sender);
							return true;
						}
						else{
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Nothing to undo");
						}
						break;
					}
				
				case "/wand":
					{
						if(!$sender->hasPermission("magicwe.command.wand")) return;
						if(empty($this->wand[$sender->getName()]) || $this->wand[$sender->getName()] === 0){
							$this->wand[$sender->getName()] = 1;
							$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Wand tool turned ON");
						}
						else{
							$this->wand[$sender->getName()] = 0;
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Wand tool toggled OFF");
						}
						return true;
						break;
					}
				
				case "/schem":
					{
						if(!$sender->hasPermission("magicwe.command.schem")) return;
						if(empty($args) || empty($args[0]) || empty($args[1])){
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Invalid option");
						}
						elseif($args[0] === "load"){
							$this->schematics[$args[1]] = $this->loadSchematic($sender, $args[1]);
							if($this->schematics[$args[1]] instanceof SchematicLoader){
								$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully loaded into cache. Use //schem paste to paste");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
							}
							return false;
						}
						elseif($args[0] === "paste"){
							if(isset($this->schematics[$args[1]]) && $this->schematics[$args[1]] instanceof SchematicLoader){
								$success = $this->pasteSchematic($sender->getLevel(), $sender, $this->schematics[$args[1]]);
								if($success){
									$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully pasted");
									return true;
								}
							}
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
							return false;
						}
						elseif($args[0] === "save" || $args[0] === "export"){
							if(isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])){
								$success = $this->exportSchematic($sender, $args[1]);
								if($success){
									$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Selection sucessfully saved as $args[1].schematic");
									return true;
								}
							}
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Can't save as $args[1]! Maybe a file wih that name already exists or you don't have write permission in this path!");
							return false;
						}
						break;
					}
				default:
					{
						return false;
					}
			}
		}
		else{
			$sender->sendMessage(TextFormat::RED . "[MagicWE] This command must be used in-game");
		}
		return false;
	}

	public function fill(Player $player, $blockarg){
		if(empty($blockarg) && $blockarg !== "0") return false;
		$level = $player->getLevel();
		$blocks = explode(",", $blockarg);
		$this->undo[$player->getName()] = [];
		$this->undopos[$player->getName()] = $this->pos1[$player->getName()];
		for($x = min($this->pos1[$player->getName()]->x, $this->pos2[$player->getName()]->x); $x <= max($this->pos1[$player->getName()]->x, $this->pos2[$player->getName()]->x); $x++){
			for($y = min($this->pos1[$player->getName()]->y, $this->pos2[$player->getName()]->y); $y <= max($this->pos1[$player->getName()]->y, $this->pos2[$player->getName()]->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = min($this->pos1[$player->getName()]->z, $this->pos2[$player->getName()]->z); $z <= max($this->pos1[$player->getName()]->z, $this->pos2[$player->getName()]->z); $z++){
					if(!$level->isChunkLoaded($x, $z)) $level->loadChunk($x, $z, true);
					$this->undo[$player->getName()][$x][$y][$z] = $level->getBlock($this->pos1[$player->getName()]->add($x, $y, $z));
					$blockstring = $blocks[array_rand($blocks, 1)];
					$block = explode(":", $blockstring)[0];
					$level->setBlockIdAt($x, $y, $z, $block);
					$meta = (isset(explode(":", $blockstring)[1])?explode(":", $blockstring)[1]:0);
					$level->setBlockDataAt($x, $y, $z, $meta);
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Fill succeed.");
	}

	public function replace(Player $player, $blockarg1, $blockarg2){
		if((empty($blockarg1) && $blockarg1 !== "0") || (empty($blockarg2) && $blockarg2 !== "0")) return false;
		$level = $player->getLevel();
		$blocks1 = explode(",", $blockarg1);
		$blocks2 = explode(",", $blockarg2);
		$this->undo[$player->getName()] = [];
		$this->undopos[$player->getName()] = $this->pos1[$player->getName()];
		for($x = min($this->pos1[$player->getName()]->x, $this->pos2[$player->getName()]->x); $x <= max($this->pos1[$player->getName()]->x, $this->pos2[$player->getName()]->x); $x++){
			for($y = min($this->pos1[$player->getName()]->y, $this->pos2[$player->getName()]->y); $y <= max($this->pos1[$player->getName()]->y, $this->pos2[$player->getName()]->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = min($this->pos1[$player->getName()]->z, $this->pos2[$player->getName()]->z); $z <= max($this->pos1[$player->getName()]->z, $this->pos2[$player->getName()]->z); $z++){
					if(!$level->isChunkLoaded($x, $z)) $level->loadChunk($x, $z, true);
					$this->undo[$player->getName()][$x][$y][$z] = $level->getBlock($this->pos1[$player->getName()]->add($x, $y, $z));
					foreach($blocks1 as $blockstring1){
						$block1 = explode(":", $blockstring1)[0];
						$meta1 = (isset(explode(":", $blockstring1)[1])?explode(":", $blockstring1)[1]:false);
						if($level->getBlockIdAt($x, $y, $z) == $block1 && ($meta1 === false || $level->getBlockDataAt($x, $y, $z) == $meta1)){
							$blockstring2 = $blocks2[array_rand($blocks2, 1)];
							$block2 = explode(":", $blockstring2)[0];
							$level->setBlockIdAt($x, $y, $z, $block2);
							$meta2 = (isset(explode(":", $blockstring2)[1])?explode(":", $blockstring2)[1]:0);
							$level->setBlockDataAt($x, $y, $z, $meta2);
						}
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Replace succeed.");
	}

	public function copy(Player $player){
		$level = $player->getLevel();
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$this->copy[$player->getName()] = [];
		$this->copypos[$player->getName()] = $pos->subtract($player->getPosition()->floor());
		for($x = 0; $x <= abs($pos1->x - $pos2->x); $x++){
			for($y = 0; $y <= abs($pos1->y - $pos2->y); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = 0; $z <= abs($pos1->z - $pos2->z); $z++){
					$this->copy[$player->getName()][$x][$y][$z] = $level->getBlock($pos->add($x, $y, $z));
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Copying succeed.");
	}

	public function paste(Player $player){
		$level = $player->getLevel();
		$pos = $player->getPosition()->add($this->copypos[$player->getName()]);
		$this->undo[$player->getName()] = [];
		$this->undopos[$player->getName()] = $pos;
		for($x = 0; $x < count(array_keys($this->copy[$player->getName()])); $x++){
			for($y = 0; $y < count(array_keys($this->copy[$player->getName()][$x])); $y++){
				if($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for($z = 0; $z < count(array_keys($this->copy[$player->getName()][$x][$y])); $z++){
					if(!$level->isChunkLoaded($x, $z)) $level->loadChunk($x, $z, true);
					$this->undo[$player->getName()][$x][$y][$z] = $level->getBlock($pos->add($x, $y, $z));
					$level->setBlockIdAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getId());
					$level->setBlockDataAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getDamage());
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Pasting succeed.");
	}

	public function undo(Player $player){
		$level = $player->getLevel();
		$pos = $this->undopos[$player->getName()];
		for($x = 0; $x < count(array_keys($this->undo[$player->getName()])); $x++){
			for($y = 0; $y < count(array_keys($this->undo[$player->getName()][$x])); $y++){
				for($z = 0; $z < count(array_keys($this->undo[$player->getName()][$x][$y])); $z++){
					$level->setBlockIdAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->undo[$player->getName()][$x][$y][$z]->getId());
					$level->setBlockDataAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->undo[$player->getName()][$x][$y][$z]->getDamage());
				}
			}
		}
		$this->undopos[$player->getName()] = null;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Undo succeed.");
	}

	public function wandPos1(BlockBreakEvent $event){
		$sender = $event->getPlayer();
		$block = $event->getBlock();
		if($sender->hasPermission("magicwe.command.wand") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1){
			if($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos1[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 1 set as x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}

	public function wandPos2(PlayerInteractEvent $event){
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
		$sender = $event->getPlayer();
		$block = $event->getBlock();
		if($sender->hasPermission("magicwe.command.wand") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1){
			if($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos2[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 2 set as x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}
	
	// structures
	public function W_sphere(Position $pos, $block, $radiusX, $radiusY, $radiusZ, $filled = true, &$output = null){
		$count = 0;
		$level = $pos->getLevel();
		
		$radiusX += 0.5;
		$radiusY += 0.5;
		$radiusZ += 0.5;
		
		$invRadiusX = 1 / $radiusX;
		$invRadiusY = 1 / $radiusY;
		$invRadiusZ = 1 / $radiusZ;
		
		$ceilRadiusX = (int) ceil($radiusX);
		$ceilRadiusY = (int) ceil($radiusY);
		$ceilRadiusZ = (int) ceil($radiusZ);
		
		// $bcnt = count ( $blocks ) - 1;
		$bcnt = 1; // only use selected block
		
		$nextXn = 0;
		$breakX = false;
		for($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x){
			$xn = $nextXn;
			$nextXn = ($x + 1) * $invRadiusX;
			$nextYn = 0;
			$breakY = false;
			for($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y){
				$yn = $nextYn;
				$nextYn = ($y + 1) * $invRadiusY;
				$nextZn = 0;
				$breakZ = false;
				for($z = 0; $z <= $ceilRadiusZ; ++$z){
					$zn = $nextZn;
					$nextZn = ($z + 1) * $invRadiusZ;
					$distanceSq = WorldEditBuilder::lengthSq($xn, $yn, $zn);
					if($distanceSq > 1){
						if($z === 0){
							if($y === 0){
								$breakX = true;
								$breakY = true;
								break;
							}
							$breakY = true;
							break;
						}
						break;
					}
					
					if($filled === false){
						if(WorldEditBuilder::lengthSq($nextXn, $yn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $nextYn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $yn, $nextZn) <= 1){
							continue;
						}
					}
					$blocktype = $block->getId();
					$this->upsetBlock2($level, $pos->add($x, $y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, $y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, -$y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, $y, -$z), $block);
					$count++;
					
					$this->upsetBlock2($level, $pos->add(-$x, -$y, $z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add($x, -$y, -$z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, $y, -$z), $block);
					$count++;
					$this->upsetBlock2($level, $pos->add(-$x, -$y, -$z), $block);
					$count++;
				}
			}
		}
		
		$output .= "$count block(s) have been changed.\n";
		// $this->log ( $output );
		return true;
	}

	public function W_cylinder(Position $pos, $block, $radius, $height, &$output){
		$changed = 0;
		for($a = -$radius; $a <= $radius; $a++){
			for($b = 0; $b < $height; $b++){
				for($c = -$radius; $c <= $radius; $c++){
					if($a * $a + $c * $c <= $radius * $radius){
						$pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, true, false);
						$changed++;
					}
				}
			}
		}
		$output = $changed . " block(s) have been created.";
	}

	public function W_holocylinder(Position $pos, $block, $radius, $height, &$output){
		$changed = 0;
		for($a = -$radius; $a <= $radius; $a++){
			for($b = 0; $b < $height; $b++){
				for($c = -$radius; $c <= $radius; $c++){
					if($a * $a + $c * $c >= ($radius - 1) * ($radius - 1)){
						$pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, true, false);
						$changed++;
					}
				}
			}
		}
		$output = $changed . " block(s) have been created.";
	}
	
	// schematic
	// TODO
	public function pasteSchematic(Level $level, Position $loc, SchematicLoader $schematic){
		$blocks = $schematic->getBlocksArray();
		foreach($blocks as $block){
			if($block[1] > self::$MAX_BUILD_HEIGHT) continue;
			if(!$level->isChunkLoaded($block[0], $block[2])) $level->loadChunk($block[0], $block[2], true);
			$level->setBlockIdAt($block[0] + $loc->getX(), $block[1] + $loc->getY(), $block[2] + $loc->getZ(), $block[3]);
			$level->setBlockDataAt($block[0] + $loc->getX(), $block[1] + $loc->getY(), $block[2] + $loc->getZ(), $block[4]);
		}
		return true;
	}

	public function loadSchematic(Player $player, $file){
		$path = $this->getDataFolder() . "/schematics/" . $file . ".schematic";
		return new SchematicLoader($this, $path);
	}

	public function exportSchematic(Player $sender, $filename){
		$blocks = '';
		$data = '';
		$pos1 = $this->pos1[$sender->getName()];
		$pos2 = $this->pos2[$sender->getName()];
		$w = max($pos1->x, $pos2->x) - min($pos1->x, $pos2->x) + 1;
		$h = max($pos1->y, $pos2->y) - min($pos1->y, $pos2->y) + 1;
		$l = max($pos1->z, $pos2->z) - min($pos1->z, $pos2->z) + 1;
		for($x = 0; $x <= $w; $x++){
			for($y = 0; $y <= $h && $y <= self::$MAX_BUILD_HEIGHT; $y++){
				for($z = 0; $z <= $l; $z++){
					$block = $sender->getLevel()->getBlock($pos1->add($x, $y, $z));
					$id = chr($block->getId());
					$damage = chr($block->getDamage());
					$blocks .= $id;
					$data .= $damage;
				}
			}
		}
		$schematic = new SchematicExporter($blocks, $data, $w, $l, $h);
		return $schematic->saveSchematic($this->getDataFolder() . "/schematics/" . $filename . ".schematic");
	}
}