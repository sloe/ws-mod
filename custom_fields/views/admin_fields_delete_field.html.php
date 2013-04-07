<?php defined("SYSPATH") or die("No direct script access.") ?>
<div id="g-admin-users-delete-user">
  <p>
    <?= t("Really delete <b>%name</b>?  Any item relations <b>(%relation_count at the moment)</b> mapped to this field <b>will get lost permanently</b>.", array("name" => $field->name, "relation_count" => $relation_count)) ?>
  </p>
  <?= $form ?>
</div>
