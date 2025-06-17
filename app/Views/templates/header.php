<?php 
use App\Helpers\BG3DirectoryHelper;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BG3LinuxHelper</title>
    <meta name="description" content="Allows Linux users to better maintain file contentuid text">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="<?= base_url('css/styles.css') ?>" type="text/css">
</head>
<body>
<header>
    <div class="menu">
        <div class="dropdown">
            <button class="dropbtn"><a class="nodrop" href="/">Home</a></button>
        </div>

        <div class="dropdown">
            <button class="dropbtn">My Mods</button>
            <div class="dropdown-content" id="dropdownMyMods">
                <?php foreach (BG3DirectoryHelper::getMyMods() as $MyMod): ?>
                    <a href="/mods/<?= esc(getenv('bg3LinuxHelper.MyMods')) ?>/<?= esc($MyMod) ?>"><?= esc($MyMod) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="dropbtn">All Mods</button>
            <div class="dropdown-content" id="dropdownAllMods">
                <?php foreach (BG3DirectoryHelper::getAllMods() as $Mod): ?>
                    <a href="/mods/<?= esc(getenv('bg3LinuxHelper.AllMods')) ?>/<?= esc($Mod) ?>"><?= esc($Mod) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="dropbtn">Game Data</button>
            <div class="dropdown-content" id="dropdownGameData">
                <?php foreach (BG3DirectoryHelper::getGameData() as $Mod): ?>
                    <a href="/mods/<?= esc(getenv('bg3LinuxHelper.GameData')) ?>/<?= esc($Mod) ?>/"><?= esc($Mod) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <div class="heroe">
            <h2><?= esc($title ?? '') ?></h2>
        </div>

        <div class="right-group">
            <div class="dropdown">
                <textarea id="UUID"></textarea>
                <button class="dropbtn" id="fetchUUID">New UUID</button>
            </div>
            <div class="dropdown">
                <textarea id="ContentUID"></textarea>
                <button class="dropbtn" id="fetchContentUID">New ContentUID</button>
            </div>
        </div>
    </div>

</header>

