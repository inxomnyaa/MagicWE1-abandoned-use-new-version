<?php

namespace MagicWE;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\ByteArray;
use pocketmine\nbt\tag\Short;
use pocketmine\nbt\tag\String;

class SchematicExporter{
	private $blocks = [];
	private $data = [];
	private $width;
	private $lenght;
	private $height;

	public function __construct($blocks, $data, $width, $lenght, $height){
		$this->blocks = $blocks;
		$this->data = $data;
		$this->width = $width;
		$this->lenght = $lenght;
		$this->height = $height;
	}

	/**
	 * Save the Schematic in the path $path
	 *
	 * @param Path $path 
	 */
	public function saveSchematic($path){
		$nbt = new NBT(NBT::BIG_ENDIAN);
		$nbt->setData(new Compound('Schematic', [new ByteArray('Blocks', $this->blocks), new ByteArray('Data', $this->data), new Short('Height', $this->height), new Short('Length', $this->lenght), new Short('Width', $this->width), new String('Materials', 'Alpha')]));
		file_put_contents($path, $nbt->writeCompressed());
		return touch($path);
	}

	/**
	 *
	 * @return the blocks
	 */
	public function getBlocks(){
		return $this->blocks;
	}

	/**
	 *
	 * @return the data
	 */
	public function getData(){
		return $this->data;
	}

	/**
	 *
	 * @return the width
	 */
	public function getWidth(){
		return $this->width;
	}

	/**
	 *
	 * @return the lenght
	 */
	public function getLenght(){
		return $this->lenght;
	}

	/**
	 *
	 * @return the height
	 */
	public function getHeight(){
		return $this->height;
	}
}