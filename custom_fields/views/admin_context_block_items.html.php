<?php defined("SYSPATH") or die("No direct script access.") ?>
<? foreach ($group as $i => $field): ?>
  <li class="g-field<? if ($context == 'all'): ?> g-draggable<? endif ?>" ref="<?= $field->id ?>">
    <?= html::clean($field->name) ?>
  </li>
<? endforeach ?>