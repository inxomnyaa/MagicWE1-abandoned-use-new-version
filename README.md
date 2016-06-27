# MagicWE (PHP7)
WorldEdit with a magic touch for PocketMine

Special thanks to [@svilex](https://github.com/svilex), helping me with the schematics!

# How is it different to other WorldEdit-plugins?
Simply this:
 - Way faster than its competitors ([World Edit](http://www.minecraftforum.net/forums/minecraft-pocket-edition/mcpe-mods-tools/2295141-world-edit-plugin-for-pocketmine-0-9-5-0-10-4-by) by MCPEGenius79), [WorldEditArt](https://github.com/PEMapModder/Small-ZC-Plugins/tree/master/WorldEditArt) by @PEMapModder (and its rewrite [WorldEditArt](https://github.com/LegendOfMCPE/WorldEditArt) by @LegendOfMCPE), [WorldEditor](https://github.com/shoghicp/WorldEditor) by @shoghicp
 - //pos1
 - //pos2
 - //wand (Fully working Wand selections using a wooden axe)
 - //copy
 - //paste
 - //undo
 - //redo
 - //set 1 | //set 1:3,2,25:5,wooden_planks:2 (randomly chosen from given id's) | //set 5:3 (metadata support) | //set grass
 - //replace 1 1 | //replace 1:5,2,45 5:3,1  (replace 1:5, 2, 45 with randomly chosen 5:3 or 1) | //replace dirt grass
 - //schem [load|paste|export]
 - Commands can use block names instead of id's
 - Copying + Pasting relative to the player
 - Estimated time + Changed blocks counter

# Planned features:
 - Fixing simple bugs on //schem load + paste
 - spheres
 - cylinders
 - hollow cylinders
 - flip / rotate clipboard
 - adding new modes to paste: //paste keep (only replaces air) | replace (default, replace all blocks) | noair (doesnt paste air blocks)
 - adding //cut
 - Async loading of schematics (Maybe not because they already load fast)

For commands see the [This issue](https://github.com/thebigsmileXD/MagicWE/issues/3)
