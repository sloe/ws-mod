<?php defined("SYSPATH") or die("No direct script access.") ?>
<div id="g-admin-custom_fields-edit-field-confirmation">
  <p>
    <?= t("Really save changes to <b>%name</b>?  The following <b>options are set to get deleted</b>. Select replacement option from the dropdown or <b>mapped item relations will get lost permanently</b>.", array("name" => $field->name)) ?>
  </p>
  <?= $form ?>
</div>
