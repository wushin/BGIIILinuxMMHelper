<input type="hidden" id="path" value="<?= esc($bginfo['path']) ?>" />

<div class="row">
  <div class="column left">
    <div class="form-row">
      <label for="search">Search:</label>
      <input spellcheck="false" id="search" />
      <button class="appSystem" onclick="search('search')">Search</button>
      <button class="appSystem" onclick="clearInput('search')">Clear</button>
    </div>

    <div class="form-row">
      <label for="replace">Replace:</label>
      <input spellcheck="false" id="replace" />
      <button class="appSystem" onclick="replace('search','replace')">Replace</button>
      <button class="appSystem" onclick="clearInput('replace')">Clear</button>
    </div>

    <div class="file-list">
      <strong>Files:</strong>
      <ul id="myUL">
        <?php if (!empty($Mods)): ?>
          <?= $Mods ?>
        <?php else: ?>
          <li><em>Unable to find any Mod Files for you.</em></li>
        <?php endif ?>
      </ul>
    </div>
  </div>

  <div class="column middle">
    <div id="displayDiv"></div>
  </div>

  <div class="column right">
    <div id="searchDiv"></div>
  </div>
</div>
