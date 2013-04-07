<?php defined("SYSPATH") or die("No direct script access.") ?>
<h4>
  <?= html::clean($context) ?>
</h4>

<? if ($context == 'all'): ?>
  <div class="g-admin-blocks-list" id="field-blocks" ref="<?= url::site("admin/custom_fields/update_field_order?csrf={$csrf}__SORTED__&context=__CONTEXT__") ?>">
  <? else: ?>
  <div class="g-admin-blocks-list">
<? endif ?>

    <? if (count($group)): ?>
      <ul class="g-field-list g-sortable-blocks" id="available-fields-<?= html::clean($context) ?>">
        <? $v = new View("admin_context_block_items.html"); $v->group = $group; $v->context = $context; ?>
        <?= $v ?>
      </ul>
    <? else: ?>
      <div>
        <p class="ui-state-disabled">
          <?= t("No field in this context yet") ?>
        </p>
      </div>
    <? endif ?>
</div>