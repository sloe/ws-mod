<?php defined("SYSPATH") or die("No direct script access.") ?>
<div class="g-block">
  <h1> <?= t("Custom Fields") ?> </h1>

  <div class="g-block-content">

    <div id="g-user-admin" class="g-block">
      <a href="<?= url::site("admin/custom_fields/add_field_form") ?>"
          class="g-dialog-link g-button g-right ui-icon-left ui-state-default ui-corner-all"
          title="<?= t("Create a new user")->for_html_attr() ?>">
        <span class="ui-icon ui-icon-circle-plus"></span>
        <?= t("Add a new field") ?>
      </a>

      <h2> <?= t("Fields") ?> </h2>

      <div class="g-block-content">
        <table id="g-field-admin-list">
          <tr>
            <th><?= t("Name") ?></th>
            <th><?= t("Type") ?></th>
            <th><?= t("Value(s)") ?></th>
            <th><?= t("Context (Album/Photo)") ?></th>
            <th><?= t("Searchable") ?></th>
            <th><?= t("Show at Creation Time") ?></th>
            <th><?= t("Show in Thumb View") ?></th>
            <th><?= t("Actions") ?></th>
          </tr>

          <? foreach ($fields as $i => $field): ?>
          <tr id="g-user-<?= $user->id ?>" class="<?= text::alternate("g-odd", "g-even") ?> g-user <?= $field->name ? "g-admin" : "" ?>">
            <td id="g-user-<?= $user->id ?>" class="g-core-info">
              <?= html::clean($field->name) ?>
            </td>
            <td>
              <?= html::clean($field->type) ?>
              <? if( isset($field->max_length) ): ?>
              (<?=$field->max_length ?>)
              <? endif ?>
            </td>
            <td>
              <? if( isset($field->options) ): ?>
                <? $length_buffer = 0; 
                   foreach ($field->options as $key => $selectionVal): ?>
                  <? print $length_buffer + strlen($selectionVal) < 70 ? $selectionVal : ($length_buffer < 70 ? '...' : '');
                     $length_buffer += strlen($selectionVal);
                     if( $key != count($field->options) && $length_buffer < 70): ?>,
                  <? else: break; ?>
                  <? endif ?>
                <? endforeach ?>
              <? else: ?>
	           	-
              <? endif ?>
            </td>

            <td>
              <?= html::clean($field->context) ?>
            </td>

            <td>
              <?= $field->searchable ? "true" : "false" ?>
            </td>

            <td>
              <?= $field->create_input ? "true" : "false" ?>
            </td>
            <td>
              <?= $field->thumb_view ? "true" : "false" ?>
            </td>
            <td>
              <a href="<?= url::site("admin/custom_fields/edit_field_form/$field->property_id") ?>"
                  open_text="<?= t("Close") ?>"
                  class="g-panel-link g-button ui-state-default ui-corner-all ui-icon-left">
                <span class="ui-icon ui-icon-pencil"></span><span class="g-button-text"><?= t("Edit") ?></span></a>
              <a href="<?= url::site("admin/custom_fields/delete_field_form/$field->property_id") ?>"
                  class="g-dialog-link g-button ui-state-default ui-corner-all ui-icon-left">
                <span class="ui-icon ui-icon-trash"></span><?= t("Delete") ?>
              </a>
            </td>
          </tr>
          <? endforeach ?>
        </table>

        <div class="g-paginator">
          <?= $pager ?>
        </div>

      </div>
    </div>

    <div id="g-group-admin" class="g-block ui-helper-clearfix">
      <? if( !empty($field_groups) ): ?>
      <h2> <?= t("Sorting Overview") ?> </h2>
      <? endif ?>

      <div class="g-block-content">
        <ul>
          <? foreach ($field_groups as $context => $group): ?>
          <li id="g-group-<?= $context ?>" class="g-group g-left">
            <? $v = new View("admin_fields_group.html"); $v->group = $group; $v->context = $context; ?>
            <?= $v ?>
          </li>
          <? endforeach ?>
        </ul>
      </div>
    </div>

  </div>
</div>
