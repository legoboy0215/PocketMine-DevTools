<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace DevTools;

use FolderPluginLoader\FolderPluginLoader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\protocol\Info;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class DevTools extends PluginBase{

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->getServer()->getLoader()->add("FolderPluginLoader", array(
			$this->getFile() . "src"
		));
		if(!class_exists("FolderPluginLoader\\FolderPluginLoader", false)){
			$this->getServer()->getPluginManager()->registerInterface("FolderPluginLoader\\FolderPluginLoader");
			$this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(), array("FolderPluginLoader\\FolderPluginLoader"));
			console("[INFO] Registered folder plugin loader");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "makeplugin":
				if(isset($args[0]) and $args[0] === "FolderPluginLoader"){
					return $this->makePluginLoader($sender, $command, $label, $args);
				}else{
					return $this->makePluginCommand($sender, $command, $label, $args);
				}
				break;
			case "makeserver":
				return $this->makeServerCommand($sender, $command, $label, $args);
				break;
			case "checkperm":
				return $this->permissionCheckCommand($sender, $command, $label, $args);
				break;
			default:
				return false;
		}
	}

	private function permissionCheckCommand(CommandSender $sender, Command $command, $label, array $args){
		$target = $sender;
		if(!isset($args[0])){
			return false;
		}
		$node = strtolower($args[0]);
		if(isset($args[1])){
			if(($player = $this->getServer()->getPlayer($args[1])) instanceof Player){
				$target = $player;
			}else{
				return false;
			}
		}

		if($target !== $sender and !$sender->hasPermission("devtools.command.checkperm.other")){
			$sender->sendMessage(TextFormat::RED . "You do not have permissions to check other players.");
			return true;
		}else{
			$sender->sendMessage(TextFormat::GREEN . "---- ".TextFormat::WHITE . "Permission node ".$node.TextFormat::GREEN. " ----");
			$perm = $this->getServer()->getPluginManager()->getPermission($node);
			if($perm instanceof Permission){
				$desc = TextFormat::GOLD . "Description: ".TextFormat::WHITE . $perm->getDescription()."\n";
				$desc .= TextFormat::GOLD . "Default: ".TextFormat::WHITE . $perm->getDefault()."\n";
				$children = "";
				foreach($perm->getChildren() as $name => $true){
					$children .= $name . ", ";
				}
				$desc .= TextFormat::GOLD . "Children: ".TextFormat::WHITE . substr($children, 0, -2)."\n";
			}else{
				$desc = TextFormat::RED . "Permission does not exist\n";
				$desc .= TextFormat::GOLD . "Default: ".TextFormat::WHITE . Permission::$DEFAULT_PERMISSION."\n";
			}
			$sender->sendMessage($desc);
			$sender->sendMessage(TextFormat::YELLOW . $target->getName() . TextFormat::WHITE . " has it set to ".($target->hasPermission($node) === true ? TextFormat::GREEN . "true" : TextFormat::RED . "false"));
			return true;
		}
	}

	private function makePluginLoader(CommandSender $sender, Command $command, $label, array $args){
		$pharPath = $this->getDataFolder() . DIRECTORY_SEPARATOR . "FolderPluginLoader.phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar plugin already exists, overwriting...");
			@unlink($pharPath);
		}
		$phar = new \Phar($pharPath);
		$phar->setMetadata([
			"name" => "FolderPluginLoader",
			"version" => "1.0.0",
			"main" => "FolderPluginLoader\\Main",
			"api" => array("1.0.0"),
			"depend" => array(),
			"description" => "Loader of folder plugins",
			"authors" => array("PocketMine Team"),
			"website" => "https://github.com/PocketMine/DevTools",
			"creationDate" => time()
		]);
		$phar->setStub('<?php __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->startBuffering();

		$phar->addFromString("plugin.yml", "name: FolderPluginLoader\nversion: 1.0.0\nmain: FolderPluginLoader\\Main\napi: [1.0.0]\nload: STARTUP\n");
		$phar->addFile($this->getFile() . "src/FolderPluginLoader/FolderPluginLoader.php", "src/FolderPluginLoader/FolderPluginLoader.php");
		$phar->addFile($this->getFile() . "src/FolderPluginLoader/Main.php", "src/FolderPluginLoader/Main.php");

		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();
		$sender->sendMessage("Folder plugin loader has been created on ".$pharPath);
		return true;
	}

	private function makePluginCommand(CommandSender $sender, Command $command, $label, array $args){
		$pluginName = trim(implode(" ", $args));
		if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)){
			$sender->sendMessage(TextFormat::RED . "Invalid plugin name, check the name case.");
			return true;
		}
		$description = $plugin->getDescription();

		if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)){
			$sender->sendMessage(TextFormat::RED . "Plugin ".$description->getName()." is not in folder structure.");
			return true;
		}

		$pharPath = $this->getDataFolder() . DIRECTORY_SEPARATOR . $description->getName()."_v".$description->getVersion().".phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar plugin already exists, overwriting...");
			@unlink($pharPath);
		}
		$phar = new \Phar($pharPath);
		$phar->setMetadata([
			"name" => $description->getName(),
			"version" => $description->getVersion(),
			"main" => $description->getMain(),
			"api" => $description->getCompatibleApis(),
			"depend" => $description->getDepend(),
			"description" => $description->getDescription(),
			"authors" => $description->getAuthors(),
			"website" => $description->getWebsite(),
			"creationDate" => time()
		]);
		$phar->setStub('<?php echo "PocketMine-MP plugin '.$description->getName() .' v'.$description->getVersion().'\nThis file has been generated using DevTools v'.$this->getDescription()->getVersion().' at '.date("r").'\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$reflection = new \ReflectionClass("pocketmine\\plugin\\PluginBase");
		$file = $reflection->getProperty("file");
		$file->setAccessible(true);
		$filePath = realpath($file->getValue($plugin));
		$phar->startBuffering();
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $file){
			$path = ltrim(str_replace(array($filePath, "\\"), array("", "/"), $file), "/");
			if($path{0} === "." or strpos($path, "/.") !== false){
				continue;
			}
			$phar->addFile($file, $path);
		}

		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();
		$sender->sendMessage("Phar plugin ".$description->getName() ." v".$description->getVersion()." has been created on ".$pharPath);
		return true;
	}

	private function makeServerCommand(CommandSender $sender, Command $command, $label, array $args){
		$server = Server::getInstance();
		$pharPath = $this->getDataFolder() . DIRECTORY_SEPARATOR . $server->getName()."_".$server->getPocketMineVersion().".phar";
		if(file_exists($pharPath)){
			$sender->sendMessage("Phar file already exists, overwriting...");
			@unlink($pharPath);
		}
		$phar = new \Phar($pharPath);
		$phar->setMetadata([
			"name" => $server->getName(),
			"version" => $server->getPocketMineVersion(),
			"api" => $server->getApiVersion(),
			"minecraft" => $server->getVersion(),
			"protocol" => Info::CURRENT_PROTOCOL,
			"creationDate" => time()
		]);
		$phar->setStub('<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->startBuffering();

		$filePath = substr(\pocketmine\PATH, 0, 7) === "phar://" ? \pocketmine\PATH : realpath(\pocketmine\PATH) . "/";
		$filePath = ltrim(str_replace("\\", "/", $filePath), "/");
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath . "src")) as $file){
			$path = ltrim(str_replace(array($filePath, "\\"), array("", "/"), $file), "/");
			if($path{0} === "." or strpos($path, "/.") !== false or substr($path, 0, 4) !== "src/"){
				continue;
			}
			$phar->addFile($file, $path);
		}
		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();

		$sender->sendMessage("PocketMine-MP Phar file has been created on ".$pharPath);

		return true;
	}

}