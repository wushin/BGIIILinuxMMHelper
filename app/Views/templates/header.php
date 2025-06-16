<?php 
use App\Models\BG3listings;
$listings = New BG3listings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BG3LinuxHelper</title>
    <meta name="description" content="Allows Linux users to better maintain file contentuid text">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="<?php echo base_url().'css/styles.css'; ?>" type="text/css">
</head>
<body>
<header>
    <div class="menu">
        <div class="dropdown">
          <button class="dropbtn"><a class="nodrop" href="/">Home</a></button>
        </div>
        <div class="dropdown">
          <button class="dropbtn">My Mods</button>
          <div id="myDropdown" class="dropdown-content">
            <?php foreach($listings->getMyMods() as $MyMod): ?>
              <a href="<?php echo "/mods/".getenv('bg3LinuxHelper.MyMods')."/".$MyMod ?>"><?php echo $MyMod ?></a>
            <?php endforeach ?>
          </div>
        </div>
        <div class="dropdown">
          <button class="dropbtn">All Mods</button>
          <div id="myDropdown" class="dropdown-content">
            <?php foreach($listings->getAllMods() as $Mod): ?>
              <a href="<?php echo "/mods/".getenv('bg3LinuxHelper.AllMods')."/".$Mod ?>"><?php echo $Mod ?></a>
            <?php endforeach ?>
          </div>
        </div>
        <div class="dropdown">
          <button class="dropbtn">Game Data</button>
          <div id="myDropdown" class="dropdown-content">
            <?php foreach($listings->getGameData() as $Mod): ?>
              <a href="<?php echo "/mods/".getenv('bg3LinuxHelper.GameData')."/".$Mod ?>/"><?php echo $Mod ?></a>
            <?php endforeach ?>
          </div>
        </div>
        <div class="dropdown" id="right">
          <textarea id="UUID"></textarea><button class="dropbtn" id="fetchUUID">New UUID</button>
        </div>
        <div class="dropdown" id="right">
          <textarea id="ContentUID"></textarea><button class="dropbtn" id="fetchContentUID">New ContentUID</button>
        </div>
    </div>
    <div class="heroe">
        <h2><?= esc($title) ?></h2>
    </div>
</header>
