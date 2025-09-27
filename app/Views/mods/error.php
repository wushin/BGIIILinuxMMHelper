<?php function h($s){return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');} ?>
<h1><?= h($pageTitle ?? 'Error') ?></h1>
<p class="flash-red"><?= h($message ?? 'Unknown error') ?></p>
