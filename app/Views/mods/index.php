<div class="heroe">
    <?php foreach ($dirs as $dir): ?>
        <p><a href="<?= esc($dir) ?>"><?= esc($dir) ?></p>
    <?php endforeach ?>
</div>
