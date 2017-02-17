<?php

namespace MagicWE;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Server;

class SchematicExporter {
	private $blocks = [];
	private $data = [];
	private $width;
	private $lenght;
	private $height;
	private $entities = [];
	private $tiles = [];

	public function __construct($blocks, $data, $width, $lenght, $height, $entities = [], $tiles = []) {
		$this->blocks = $blocks;
		$this->data = $data;
		$this->width = $width;
		$this->lenght = $lenght;
		$this->height = $height;
		$this->entities = $entities;
		$this->tiles = $tiles;
	}

	/**
	 * Save the Schematic in the path $path
	 *
	 * @param Path $path
	 */
	public function saveSchematic($path) {
		$nbt = new NBT(NBT::BIG_ENDIAN);
		$compound = new CompoundTag('Schematic', [new ByteArrayTag('Blocks', $this->blocks), new ByteArrayTag('Data', $this->data), new ShortTag('Height', $this->height), new ShortTag('Length', $this->lenght), new ShortTag('Width', $this->width), new StringTag('Materials', 'Alpha')]);
		$compound->Entities = new ListTag("Entities", $this->entities);
		$compound->Entities->setTagType(NBT::TAG_Compound);
		$compound->TileEntities = new ListTag("TileEntities", $this->tiles);
		$compound->TileEntities->setTagType(NBT::TAG_Compound);
		$nbt->setData($compound);
		#Server::getInstance()->getLogger()->debug(var_export($nbt, true));
		file_put_contents($path, $nbt->writeCompressed());
		return touch($path);
	}

	/**
	 *
	 * @return the blocks
	 */
	public function getBlocks() {
		return $this->blocks;
	}

	/**
	 *
	 * @return the data
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 *
	 * @return the entities
	 */
	public function getEntities() {
		return $this->entities;
	}

	/**
	 *
	 * @return the tiles
	 */
	public function getTiles() {
		return $this->tiles;
	}

	/**
	 *
	 * @return the width
	 */
	public function getWidth() {
		return $this->width;
	}

	/**
	 *
	 * @return the lenght
	 */
	public function getLenght() {
		return $this->lenght;
	}

	/**
	 *
	 * @return the height
	 */
	public function getHeight() {
		return $this->height;
	}
}