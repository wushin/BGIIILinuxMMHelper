<?php
/**
 * BGIII Mod Manager Linux Helper — base layout restoring header/footer & CSS
 * GPLv3-or-later
 *
 * Expects: $pageTitle (string), optional $activeTab (string)
 */

use function htmlspecialchars as h;

// Build dropdowns directly here to keep all pages consistent with header UI.
$paths = service('pathResolver');
$rootsAbs = [
    'MyMods'       => $paths->myMods(),
    'AllMods'      => $paths->unpackedMods(), // alias, links still say "All Mods"
];

function list_mod_slugs(?string $abs, bool $dirsOnly = true) : array {
    if (!$abs || !is_dir($abs)) return [];
    $out = [];
    foreach (scandir($abs) ?: [] as $n) {
        if ($n === '.' || $n === '..' || $n[0] === '.') continue; // hide dot entries like .git
        $p = $abs . DIRECTORY_SEPARATOR . $n;
        if (is_dir($p)) {
            $out[] = $n;                // keep only directories
        } elseif (!$dirsOnly) {
            // only used if you ever want files too
            $out[] = $n;
        }
    }
    natcasesort($out);
    return array_values($out);
}

$myMods    = list_mod_slugs($rootsAbs['MyMods'], true);
$allMods   = list_mod_slugs($rootsAbs['AllMods'], true);
$title     = isset($pageTitle) ? (string)$pageTitle : 'BG3LinuxHelper';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= h($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" type="image/png" href="/favicon.ico" />
  <!-- Restore original CSS -->
  <link rel="stylesheet" href="/css/styles.css" />
  <link rel="stylesheet" href="/css/searchPopup.css" />
  <link rel="stylesheet" href="/css/github-markdown.min.css" />
</head>
<body>
<header>
  <div class="menu">
    <div class="dropdown">
      <a class="nodrop" href="/"><button class="dropbtn">Home</button></a>
    </div>
    <div class="dropdown">
      <a class="nodrop" href="/settings/"><button class="dropbtn">Settings</button></a>
    </div>

    <div class="dropdown">
      <button class="dropbtn">My Mods</button>
      <div class="dropdown-content" id="dropdownMyMods">
        <input type="text" id="MyModFilter" placeholder="Search mods..." onkeyup="filterDropdown(this)" style="width:100%;box-sizing:border-box;padding:8px;border:none;border-bottom:1px solid #ccc;">
        <?php foreach ($myMods as $slug): ?>
          <a href="/mods/MyMods/<?= h($slug) ?>"><?= h($slug) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dropdown">
      <button class="dropbtn">All Mods</button>
      <div class="dropdown-content" id="dropdownAllMods">
        <input type="text" id="ModFilter" placeholder="Search mods..." onkeyup="filterDropdown(this)" style="width:100%;box-sizing:border-box;padding:8px;border:none;border-bottom:1px solid #ccc;">
        <?php foreach ($allMods as $slug): ?>
          <a href="/mods/AllMods/<?= h($slug) ?>"><?= h($slug) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dropdown">
      <button class="dropbtn" id="btnGameData" onclick="toggleMongoPopup()">Game Data</button>
    </div>

    <div class="heroe">
      <h2><?= h($title) ?></h2>
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

<!-- Page content -->
<main class="container" style="padding:0.5rem 1rem 1rem;">
  <?= $this->renderSection('content') ?>
</main>

<footer>
  <div class="environment">BGIII Mod Manager Linux Helper</div>
  <div class="copyrights">© <?= date('Y') ?> Wushin — GPLv3-or-later</div>
</footer>

<!-- Original scripts -->
<script src="/js/marked.min.js"></script>
<script src="/js/searchPopup.js"></script>
<script src="/js/bg3.js"></script>
</body>
</html>

