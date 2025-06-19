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
    <link rel="stylesheet" href="/css/styles.css" type="text/css">
    <link rel="stylesheet" href="/css/searchPopup.css" type="text/css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css">
</head>
<body>
<header>
    <div class="menu">
        <div class="dropdown">
            <a class="nodrop" href="/"><button class="dropbtn">Home</button></a>
        </div>

        <div class="dropdown">
            <button class="dropbtn">My Mods</button>
            <div class="dropdown-content" id="dropdownMyMods">
              <input type="text" id="MyModFilter" placeholder="Search mods..." onkeyup="filterDropdown(this)" style="width: 100%; box-sizing: border-box; padding: 8px; border: none; border-bottom: 1px solid #ccc;">
                <?php foreach (BG3DirectoryHelper::getMyMods() as $MyMod): ?>
                    <a href="/mods/<?= esc(getenv('bg3LinuxHelper.MyMods')) ?>/<?= esc($MyMod) ?>"><?= esc($MyMod) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="dropbtn">All Mods</button>
            <div class="dropdown-content" id="dropdownAllMods">
              <input type="text" id="ModFilter" placeholder="Search mods..." onkeyup="filterDropdown(this)" style="width: 100%; box-sizing: border-box; padding: 8px; border: none; border-bottom: 1px solid #ccc;">
                <?php foreach (BG3DirectoryHelper::getAllMods() as $Mod): ?>
                    <a href="/mods/<?= esc(getenv('bg3LinuxHelper.AllMods')) ?>/<?= esc($Mod) ?>"><?= esc($Mod) ?></a>
                <?php endforeach ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="dropbtn" id="btnGameData" onclick="toggleMongoPopup()">Game Data</button>
        </div>

        <div class="heroe">
            <h2><?= esc($title ?? '') ?></h2>
        </div>

        <div class="right-group">
            <div class="dropdown">
                <textarea spellcheck="false" id="UUID"></textarea>
                <button class="dropbtn" id="fetchUUID">New UUID</button>
            </div>
            <div class="dropdown">
                <textarea spellcheck="false" id="ContentUID"></textarea>
                <button class="dropbtn" id="fetchContentUID">New ContentUID</button>
            </div>
        </div>
    </div>

</header>

