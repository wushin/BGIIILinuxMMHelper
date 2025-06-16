<input type="hidden" id="path" value="<?php echo $bginfo['path'] ?>"/>
<div class="row">
  <div class="column left">
    <ul>
      <li>Search:</li>
      <li><input id="search"></input><button onclick="search('search')">Search</button><button onclick="clearInput('search')">Clear</button></li>
    </ul>
    <ul>
      <li>Replace:</li>
      <li><input id="replace"></input><button onclick="replace('search','replace')">Replace</button><button onclick="clearInput('replace')">Clear</button></li>
    </ul>
    <ul>
      <?php if ($Mods !== ""): ?>
    <ul id="myUL">
        <li>Files:</li>
        <?php echo $Mods; ?>
    </ul>
      <?php else: ?>
    <ul>
        <p>Unable to find any Mod Files for you.</p>
    </ul>
      <?php endif ?>
  </div>
  <div class="column middle">
    <div id="displayDiv"></div>
  </div>
  <div class="column right">
    <div id="searchDiv"></div>
  </div>
</div>
