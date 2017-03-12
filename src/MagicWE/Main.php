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
 * Thanks for all suggestions from you all!
 *
 * https://github.com/thebigsmileXD
 * https://github.com/svilex
 */
namespace MagicWE;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {
	public $areas;
	private $pos1 = [], $pos2 = [], $copy = [], $copypos = [], $undo = [], $redo = [], $wand = [], $schematics = [];
	private static $MAX_BUILD_HEIGHT = Level::Y_MAX;

	public function onLoad() {
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE has been loaded!");
	}

	public function onEnable() {
		$this->saveResource("config.yml");
		@mkdir($this->getDataFolder() . "schematics");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GREEN . "MagicWE enabled!");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool{
		if ($sender instanceof Player) {
			switch ($command) {
				case "/pos1": {
					if (!$sender->hasPermission("we.command.pos1") && !$sender->hasPermission("we.command.admin")) return false;
					$pos1x = $sender->getFloorX();
					$pos1y = $sender->getFloorY();
					$pos1z = $sender->getFloorZ();
					$this->pos1[$sender->getName()] = new Vector3($pos1x, $pos1y, $pos1z);
					if ($pos1y > self::$MAX_BUILD_HEIGHT || $pos1y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
					$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 1 set as x:" . $pos1x . " y:" . $pos1y . " z:" . $pos1z);
					return true;
					break;
				}

				case "/pos2": {
					if (!$sender->hasPermission("we.command.pos2") && !$sender->hasPermission("we.command.admin")) return false;
					$pos2x = $sender->getFloorX();
					$pos2y = $sender->getFloorY();
					$pos2z = $sender->getFloorZ();
					$this->pos2[$sender->getName()] = new Vector3($pos2x, $pos2y, $pos2z);
					if ($pos2y > self::$MAX_BUILD_HEIGHT || $pos2y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
					$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 2 set as x:" . $pos2x . " y:" . $pos2y . " z:" . $pos2z);
					return true;
					break;
				}

				case "/set": {
					if (!$sender->hasPermission("we.command.set") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($args[0])) {
						if (isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])) {
							$this->fill($sender, $args[0]);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
					}
					break;
				}

				case "/replace": {
					if (!$sender->hasPermission("we.command.replace") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($args[0]) && isset($args[1])) {
						if (isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])) {
							$this->replace($sender, $args[0], $args[1]);
							$sender->getLevel()->doChunkGarbageCollection();
							return true;
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
					}
					break;
				}

				case "/copy": {
					if (!$sender->hasPermission("we.command.copy") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])) {
						$this->copy($sender);
						return true;
					}
					break;
				}

				case "/paste": {
					if (!$sender->hasPermission("we.command.paste") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])) {
						$this->paste($sender);
						$sender->getLevel()->doChunkGarbageCollection();
						return true;
					}
					break;
				}

				case "/undo": {
					if (!$sender->hasPermission("we.command.undo") && !$sender->hasPermission("we.command.admin")) return false;
					if (!empty($this->undo[$sender->getName()])) {
						$this->undo($sender);
						$sender->getLevel()->doChunkGarbageCollection();
						return true;
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Nothing to undo");
					}
					break;
				}

				case "/redo": {
					if (!$sender->hasPermission("we.command.redo") && !$sender->hasPermission("we.command.admin")) return false;
					if (!empty($this->redo[$sender->getName()])) {
						$this->redo($sender);
						$sender->getLevel()->doChunkGarbageCollection();
						return true;
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Nothing to redo");
					}
					break;
				}

				case "/flip": {
					if (!$sender->hasPermission("we.command.flip") && !$sender->hasPermission("we.command.admin")) return false;
					if (!empty($this->copy[$sender->getName()]) && isset($args[0])) {
						if (!in_array($args[0], array("x", "y", "z"))) return false;
						$this->flip($sender, $args[0]);
						return true;
					} elseif (!isset($args[0])) {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] invalid argments");
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Nothing to flip, use //copy first");
					}
					break;
				}

				case "toggleeditwand":
				case "/wand": {
					if (!$sender->hasPermission("we.command.wand") && !$sender->hasPermission("we.command.admin")) return false;
					if (empty($this->wand[$sender->getName()]) || $this->wand[$sender->getName()] === 0) {
						$this->wand[$sender->getName()] = 1;
						$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Wand tool turned ON");
					} else {
						$this->wand[$sender->getName()] = 0;
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Wand tool toggled OFF");
					}
					return true;
					break;
				}

				case "/schem": {
					if (!$sender->hasPermission("we.command.schem") && !$sender->hasPermission("we.command.admin")) return false;
					if (empty($args) || empty($args[0]) || empty($args[1])) {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Invalid option");
					} elseif ($args[0] === "load") {
						$this->schematics[$args[1]] = $this->loadSchematic($sender, $args[1]);
						if ($this->schematics[$args[1]] instanceof SchematicLoader) {
							$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully loaded into cache. Use //schem paste $args[1] to paste");
							return true;
						} else {
							$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
						}
						return false;
					} elseif ($args[0] === "paste") {
						if (isset($this->schematics[$args[1]]) && $this->schematics[$args[1]] instanceof SchematicLoader) {
							var_dump($sender->getPosition());
							$success = $this->pasteSchematic($sender, $sender->getLevel(), $sender->getPosition(), $this->schematics[$args[1]]);
							if ($success) {
								$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Schematic $args[1] sucessfully pasted");
								$sender->getLevel()->doChunkGarbageCollection();
								return true;
							}
						}
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Incorrect schematic file or not loaded. Use //schem load <filename> to load a schematic");
						return false;
					} elseif ($args[0] === "save" || $args[0] === "export") {
						if (isset($this->pos1[$sender->getName()], $this->pos2[$sender->getName()])) {
							$success = $this->exportSchematic($sender, $args[1]);
							if ($success) {
								$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Selection sucessfully saved as $args[1].schematic");
								return true;
							}
						}
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Can't save as $args[1]! Maybe a file wih that name already exists or you don't have write permission in this path!");
						return false;
					}
					break;
				}

				case "/cyl": {
					if (!$sender->hasPermission("we.command.cyl") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($args[0], $args[1])) {
						#$this->fill($sender, $args[0]);
						$this->W_cylinder($sender, $sender->getPosition(), $args[0], $args[1], $args[2]??1);
						$sender->getLevel()->doChunkGarbageCollection();
						return true;
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
					}
					break;
				}

				case "/hcyl": {
					if (!$sender->hasPermission("we.command.hcyl") && !$sender->hasPermission("we.command.admin")) return false;
					if (isset($args[0], $args[1])) {
						#$this->fill($sender, $args[0]);
						$this->W_holocylinder($sender, $sender->getPosition(), $args[0], $args[1], $args[2]??1);
						$sender->getLevel()->doChunkGarbageCollection();
						return true;
					} else {
						$sender->sendMessage(TextFormat::RED . "[MagicWE] Missing arguments");
					}
					break;
				}
				default: {
					return false;
				}
			}
		} else {
			$sender->sendMessage(TextFormat::RED . "[MagicWE] This command must be used in-game");
		}
		return false;
	}

	public function wandPos1(BlockBreakEvent $event) {
		$sender = $event->getPlayer();
		$block = $event->getBlock()->floor();
		if ($sender->hasPermission("we.command.wand") && !$sender->hasPermission("we.command.admin") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1) {
			if ($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos1[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 1 set as x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}

	public function wandPos2(PlayerInteractEvent $event) {
		if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
		$sender = $event->getPlayer();
		$block = $event->getBlock()->floor();
		if ($sender->hasPermission("we.command.wand") && !$sender->hasPermission("we.command.admin") && $sender->getInventory()->getItemInHand()->getId() === Item::WOODEN_AXE && $this->wand[$sender->getName()] === 1) {
			if ($block->y > self::$MAX_BUILD_HEIGHT || $block->y < 0) $sender->sendMessage(TextFormat::GOLD . "[MagicWE] Warning: You are above y:" . self::$MAX_BUILD_HEIGHT . " or below y:0");
			$this->pos2[$sender->getName()] = $block;
			$sender->sendMessage(TextFormat::GREEN . "[MagicWE] Position 2 set as x:" . $block->x . " y:" . $block->y . " z:" . $block->z);
			$event->setCancelled();
		}
	}

	public function fill(Player $player, $blockarg) {
		$changed = 0;
		$time = microtime(TRUE);
		if (empty($blockarg) && $blockarg !== "0") return false;
		$level = $player->getLevel();
		$blocks = explode(",", $blockarg);
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		if (!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		for ($x = $pos->x; $x <= max($pos1->x, $pos2->x); $x++) {
			for ($y = $pos->y; $y <= max($pos1->y, $pos2->y); $y++) {
				if ($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for ($z = $pos->z; $z <= max($pos1->z, $pos2->z); $z++) {
					if (!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($vec = new Vector3($x, $y, $z)));
					$blockstring = $blocks[array_rand($blocks, 1)];
					$block = Item::fromString($blockstring)->getBlock();
					if ($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")) {
						$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
						$player->sendMessage(TextFormat::RED . "[MagicWE] Fill failed.");
						return;
					}
					// $level->setBlockIdAt($x, $y, $z, $block->getId());
					// $level->setBlockDataAt($x, $y, $z, $block->getDamage());
					if ($level->setBlock($vec, $block, false, false)) $changed++;
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Fill succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function replace(Player $player, $blockarg1, $blockarg2) {
		$changed = 0;
		$time = microtime(TRUE);
		if ((empty($blockarg1) && $blockarg1 !== "0") || (empty($blockarg2) && $blockarg2 !== "0")) return false;
		$level = $player->getLevel();
		$blocks1 = explode(",", $blockarg1);
		$blocks2 = explode(",", $blockarg2);
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		if (!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		for ($x = $pos->x; $x <= max($pos1->x, $pos2->x); $x++) {
			for ($y = $pos->y; $y <= max($pos1->y, $pos2->y); $y++) {
				if ($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for ($z = $pos->z; $z <= max($pos1->z, $pos2->z); $z++) {
					if (!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($vec = new Vector3($x, $y, $z)));
					foreach ($blocks1 as $blockstring1) {
						$blocka = Item::fromString($blockstring1)->getBlock();
						if ($blocka->getId() === 0 && !(strtolower(explode(":", $blockstring1)[0]) == "air" || explode(":", $blockstring1)[0] == "0")) {
							$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring1 . '", aborting');
							$player->sendMessage(TextFormat::RED . "[MagicWE] Replace failed.");
							return;
						}
						$block1 = $blocka->getId();
						$meta1 = (explode(":", $blockstring1)[1]??false);
						if ($level->getBlockIdAt($x, $y, $z) == $block1 && ($meta1 === false || $level->getBlockDataAt($x, $y, $z) == $meta1)) {
							$blockstring2 = $blocks2[array_rand($blocks2, 1)];
							$blockb = Item::fromString($blockstring2)->getBlock();
							if ($blockb->getId() === 0 && !(strtolower(explode(":", $blockstring2)[0]) == "air" || explode(":", $blockstring2)[0] == "0")) {
								$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring2 . '", aborting');
								$player->sendMessage(TextFormat::RED . "[MagicWE] Replace failed.");
								return;
							}
							// $block2 = $blockb->getId();
							if ($level->setBlock($vec, $blockb, false, false)) $changed++;
							// $level->setBlockIdAt($x, $y, $z, $block2);
							// $meta2 = (explode(":", $blockstring2)[1]??0);
							// $level->setBlockDataAt($x, $y, $z, $meta2);
						}
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Replace succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function copy(Player $player) {
		$level = $player->getLevel();
		$pos1 = $this->pos1[$player->getName()];
		$pos2 = $this->pos2[$player->getName()];
		$pos = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$this->copy[$player->getName()] = [];
		$this->copypos[$player->getName()] = $pos->subtract($player->getPosition()->floor());
		for ($x = 0; $x <= abs($pos1->x - $pos2->x); $x++) {
			for ($y = 0; $y <= abs($pos1->y - $pos2->y); $y++) {
				if ($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for ($z = 0; $z <= abs($pos1->z - $pos2->z); $z++) {
					$this->copy[$player->getName()][$x][$y][$z] = $level->getBlock($pos->add($x, $y, $z));
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Copying succeed.");
	}

	public function paste(Player $player) {
		$time = microtime(TRUE);
		$level = $player->getLevel();
		$pos = $player->getPosition()->add($this->copypos[$player->getName()]);
		if (!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		for ($x = 0; $x < count(array_keys($this->copy[$player->getName()])); $x++) {
			for ($y = 0; $y < count(array_keys($this->copy[$player->getName()][$x])); $y++) {
				if ($y > self::$MAX_BUILD_HEIGHT || $y < 0) continue;
				for ($z = 0; $z < count(array_keys($this->copy[$player->getName()][$x][$y])); $z++) {
					if (!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);
					array_push($this->undo[$player->getName()][$undoindex], $level->getBlock(new Vector3($x, $y, $z)));
					$level->setBlockIdAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getId());
					$level->setBlockDataAt($pos->x + $x, $pos->y + $y, $pos->z + $z, $this->copy[$player->getName()][$x][$y][$z]->getDamage());
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Pasting succeed, took " . round((microtime(TRUE) - $time), 2) . "s");
	}

	public function undo(Player $player) {
		$time = microtime(TRUE);
		$level = $player->getLevel();
		if (!isset($this->undo[$player->getName()])) return;
		$undo = array_pop($this->undo[$player->getName()]);
		foreach ($undo as $block) {
			$level->setBlockIdAt($block->x, $block->y, $block->z, $block->getId());
			$level->setBlockDataAt($block->x, $block->y, $block->z, $block->getDamage());
		}
		$this->redo[$player->getName()][] = $undo;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Undo succeed, took " . round((microtime(TRUE) - $time), 2) . "s");
	}

	public function redo(Player $player) {
		$time = microtime(TRUE);
		$level = $player->getLevel();
		if (!isset($this->redo[$player->getName()])) return;
		$redo = array_pop($this->redo[$player->getName()]);
		foreach ($redo as $block) {
			$level->setBlockIdAt($block->x, $block->y, $block->z, $block->getId());
			$level->setBlockDataAt($block->x, $block->y, $block->z, $block->getDamage());
		}
		$this->undo[$player->getName()] = $redo;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Redo succeed, took " . round((microtime(TRUE) - $time), 2) . "s");
	}

	public function flip(Player $player, $xyz) {
		if ($xyz === "x") {
			$this->copy[$player->getName()] = array_reverse($this->copy[$player->getName()]);
		} elseif ($xyz === "y") {
			foreach (array_keys($this->copy[$player->getName()]) as $block) {
				$this->copy[$player->getName()][$block] = array_reverse($this->copy[$player->getName()][$block]);
			}
		} elseif ($xyz === "z") {
			foreach (array_keys($this->copy[$player->getName()]) as $block) {
				foreach (array_keys($this->copy[$player->getName()][$block]) as $y) {
					$this->copy[$player->getName()][$block][$y] = array_reverse($this->copy[$player->getName()][$block][$y]);
				}
			}
		} else
			return false;
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Clipboard flipped on $xyz-Axis");
	}

	// structures
	public function W_sphere(Player $player, Position $pos, $blockarg, $radiusX, $radiusY, $radiusZ, $filled = true, &$output = null) {
		$changed = 0;
		$time = microtime(TRUE);
		$blocks = explode(",", $blockarg);
		$block = Item::fromString($blocks[0])->getBlock();
		if ($block->getId() === 0 && !(strtolower(explode(":", $block[0])[0]) == "air" || explode(":", $block[0])[0] == "0")) {
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $block[0] . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating sphere failed.");
			return;
		}
		$level = $pos->getLevel();

		$radiusX += 0.5;
		$radiusY += 0.5;
		$radiusZ += 0.5;

		$invRadiusX = 1 / $radiusX;
		$invRadiusY = 1 / $radiusY;
		$invRadiusZ = 1 / $radiusZ;

		$ceilRadiusX = (int)ceil($radiusX);
		$ceilRadiusY = (int)ceil($radiusY);
		$ceilRadiusZ = (int)ceil($radiusZ);

		// $bcnt = count ( $blocks ) - 1;
		$bcnt = 1; // only use selected block

		$nextXn = 0;
		$breakX = false;
		for ($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x) {
			$xn = $nextXn;
			$nextXn = ($x + 1) * $invRadiusX;
			$nextYn = 0;
			$breakY = false;
			for ($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y) {
				$yn = $nextYn;
				$nextYn = ($y + 1) * $invRadiusY;
				$nextZn = 0;
				$breakZ = false;
				for ($z = 0; $z <= $ceilRadiusZ; ++$z) {
					$zn = $nextZn;
					$nextZn = ($z + 1) * $invRadiusZ;
					$distanceSq = WorldEditBuilder::lengthSq($xn, $yn, $zn);//TODO
					if ($distanceSq > 1) {
						if ($z === 0) {
							if ($y === 0) {
								$breakX = true;
								$breakY = true;
								break;
							}
							$breakY = true;
							break;
						}
						break;
					}

					if ($filled === false) {
						if (WorldEditBuilder::lengthSq($nextXn, $yn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $nextYn, $zn) <= 1 and WorldEditBuilder::lengthSq($xn, $yn, $nextZn) <= 1) {
							continue;
						}
					}
					$blocktype = $block->getId();
					$count = 0;
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
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating sphere succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function W_cylinder(Player $player, Position $pos, $blockstring, $radius, $height) {
		$changed = 0;
		$time = microtime(TRUE);
		$block = Item::fromString($blockstring)->getBlock();
		if ($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")) {
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating cylinder failed.");
			return;
		}
		for ($a = -$radius; $a <= $radius; $a++) {
			for ($b = 0; $b < $height; $b++) {
				for ($c = -$radius; $c <= $radius; $c++) {
					if ($a * $a + $c * $c <= $radius * $radius) {
						if ($pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, false, false)) $changed++;
						$changed++;
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating cylinder succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	public function W_holocylinder(Player $player, Position $pos, $blockstring, $radius, $height) {
		$changed = 0;
		$time = microtime(TRUE);
		$block = Item::fromString($blockstring)->getBlock();
		if ($block->getId() === 0 && !(strtolower(explode(":", $blockstring)[0]) == "air" || explode(":", $blockstring)[0] == "0")) {
			$player->sendMessage(TextFormat::RED . '[MagicWE] No such block/item found: "' . $blockstring . '", aborting');
			$player->sendMessage(TextFormat::RED . "[MagicWE] Creating cylinder failed.");
			return;
		}
		$changed = 0;
		for ($a = -$radius; $a <= $radius; $a++) {
			for ($b = 0; $b < $height; $b++) {
				for ($c = -$radius; $c <= $radius; $c++) {
					$rad = $a * $a + $c * $c;
					if (($rad >= (($radius - 1) * ($radius - 1))) && ($rad <= ($radius * $radius))) {
						if ($pos->getLevel()->setBlock(new Position($pos->x + $a, $pos->y + $b, $pos->z + $c, $pos->getLevel()), $block, false, false)) $changed++;
					}
				}
			}
		}
		$player->sendMessage(TextFormat::GREEN . "[MagicWE] Creating hollow cylinder succeed, took " . round((microtime(TRUE) - $time), 2) . "s, " . $changed . " Blocks changed.");
	}

	// schematic
	// TODO
	public function pasteSchematic(Player $player, Level $level, Position $loc, SchematicLoader $schematic) {
		$blocks = $schematic->getBlocksArray();
		$entities = $schematic->getEntitiesArray();
		$tiles = $schematic->getTilesArray();
		var_dump($tiles);
		$loc = $loc->floor();
		if (!isset($this->undo[$player->getName()])) $this->undo[$player->getName()] = [];
		$undoindex = count(array_keys($this->undo[$player->getName()]));
		$this->undo[$player->getName()][$undoindex] = [];
		foreach ($blocks as $block) {
			if ($block[1] > self::$MAX_BUILD_HEIGHT) continue;
			if (!$level->isChunkLoaded($block[0] >> 4, $block[2] >> 4)) $level->loadChunk($block[0] >> 4, $block[2] >> 4, true);
			$blockloc = $loc->add($block[0], $block[1], $block[2]);
			array_push($this->undo[$player->getName()][$undoindex], $level->getBlock($blockloc));
			$level->setBlockIdAt($blockloc->getX(), $blockloc->getY(), $blockloc->getZ(), $block[3]);
			$level->setBlockDataAt($blockloc->getX(), $blockloc->getY(), $blockloc->getZ(), $block[4]);
		}
		/** @var CompoundTag $nbt */
		foreach ($tiles as $nbt) {//TODO: Fix that these aren't pasted the second time. Reason: fucking increasing position
			$blockloc = $loc->add($nbt->x->getValue(), $nbt->y->getValue(), $nbt->z->getValue());
			#var_dump($blockloc);
			if ($blockloc->y > self::$MAX_BUILD_HEIGHT) continue;
			#$nbt->x->setValue($blockloc->x);
			#$nbt->y->setValue($blockloc->y);
			#$nbt->z->setValue($blockloc->z);//Update the positions
			//TODO: I need help here. On a second run, the values are DOUBLED!
			/*if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);*///Already loaded before, may be useless
			$tile = Tile::createTile($nbt->id->getValue(), $level->getChunk($blockloc->x >> 4, $blockloc->z >> 4), $nbt);//TODO: PC -> PE ID conversion
			if ($tile instanceof Tile){
				$this->getLogger()->debug('Spawned a/an ' . $tile->getSaveId() . ' tile');
				$this->getLogger()->debug('TilePos:' . $tile->x . ":" . $tile->y . ":" . $tile->z);
			}
		}
		foreach ($entities as $nbt) {//TODO: Fix that these aren't pasted the second time. Reason: fucking increasing position
			$blockloc = $loc->add($nbt->Pos[0], $nbt->Pos[1], $nbt->Pos[2]);
			if ($blockloc->y > self::$MAX_BUILD_HEIGHT) continue;
			#$nbt->Pos[0] = $blockloc->x;
			#$nbt->Pos[1] = $blockloc->y;
			#$nbt->Pos[2] = $blockloc->z;//Update the positions
			//TODO: I need help here. On a second run, the values are DOUBLED!
			/*if(!$level->isChunkLoaded($x >> 4, $z >> 4)) $level->loadChunk($x >> 4, $z >> 4, true);*///Already loaded before, may be useless
			$entity = Entity::createEntity($nbt->id->getValue(), $level->getChunk($blockloc->x >> 4, $blockloc->z >> 4), $nbt);//TODO: PC -> PE ID conversion
			if ($entity instanceof Entity){
				$this->getLogger()->debug('Spawned a/an ' . $entity->getSaveId() . ' entity');
				$this->getLogger()->debug('EntityPos:'.$entity->x.":".$entity->y.":".$entity->z);
			}
		}
		return true;
	}

	public function loadSchematic(Player $player, $file) {
		$path = $this->getDataFolder() . "schematics/" . $file . ".schematic";
		return new SchematicLoader($this, $path);
	}

	public function exportSchematic(Player $sender, $filename) {
		$blocks = '';
		$entities = [];
		$tiles = [];
		$data = '';
		$pos1 = $this->pos1[$sender->getName()];
		$pos2 = $this->pos2[$sender->getName()];
		$origin = new Vector3(min($pos1->x, $pos2->x), min($pos1->y, $pos2->y), min($pos1->z, $pos2->z));
		$origintarget = new Vector3(max($pos1->x, $pos2->x), max($pos1->y, $pos2->y), max($pos1->z, $pos2->z));
		$w = abs($pos1->x - $pos2->x) + 1;
		$h = abs($pos1->y - $pos2->y) + 1;
		$l = abs($pos1->z - $pos2->z) + 1;
		for ($y = 0; $y < $h; $y++) {
			for ($z = 0; $z < $l; $z++) {
				for ($x = 0; $x < $w; $x++) {
					$block = $sender->getLevel()->getBlock($origin->add($x, $y, $z));
					$id = $block->getId();
					$damage = $block->getDamage();
					switch ($id) {
						case 158:
							$id = 126;
							break;
						case 157:
							$id = 125;
							break;
						case 126:
							$id = 157;
							break;
						case 85:
							switch ($damage) {
								case 1:
									$id = 188;
									$damage = 0;
									break;
								case 2:
									$id = 189;
									$damage = 0;
									break;
								case 3:
									$id = 190;
									$damage = 0;
									break;
								case 4:
									$id = 191;
									$damage = 0;
									break;
								case 5:
									$id = 192;
									$damage = 0;
									break;
								default:
									$damage = 0;
									break;
							}
							break;
						default:
							break;
					}
					$blocks .= chr($id);
					$data .= chr($damage);
				}
			}
		}
		/** @var Tile $tile */
		foreach ($sender->getLevel()->getTiles() as $tile) {
			if (($tile->x >= $origin->x && $tile->x <= $origintarget->x) && ($tile->y >= $origin->y && $tile->y <= $origintarget->y) && ($tile->z >= $origin->z && $tile->z <= $origintarget->z)) {
				$tile->namedtag->x->setValue(abs($tile->namedtag->x->getValue() - $origin->x));
				$tile->namedtag->y->setValue(abs($tile->namedtag->y->getValue() - $origin->y));
				$tile->namedtag->z->setValue(abs($tile->namedtag->z->getValue() - $origin->z));
				$this->getLogger()->debug('TilePos:'.$tile->namedtag->x->getValue().":".$tile->namedtag->y->getValue().":".$tile->namedtag->z->getValue());
				$tiles[] = $tile->namedtag;//TODO: xyz = offset
			}
		}
		/** @var Entity $tile */
		foreach ($sender->getLevel()->getEntities() as $tile) {
			if (($tile->x >= $origin->x && $tile->x <= $origintarget->x) && ($tile->y >= $origin->y && $tile->y <= $origintarget->y) && ($tile->z >= $origin->z && $tile->z <= $origintarget->z)) {
				$tile->namedtag->Pos[0] = $tile->namedtag->Pos[0] - $origin->x;
				$tile->namedtag->Pos[1] = $tile->namedtag->Pos[1] - $origin->y;
				$tile->namedtag->Pos[2] = $tile->namedtag->Pos[2] - $origin->z;
				$this->getLogger()->debug('EntityPos:'.$tile->namedtag->Pos[0].":".$tile->namedtag->Pos[1].":".$tile->namedtag->Pos[2]);
				$entities[] = $tile->namedtag;//TODO: xyz = offset
			}
		}
		$schematic = new SchematicExporter($blocks, $data, $w, $l, $h, $entities, $tiles);
		return $schematic->saveSchematic(str_replace("//", "/", str_replace("\\", "/", $this->getDataFolder() . "/schematics/" . $filename . ".schematic")));
	}
}