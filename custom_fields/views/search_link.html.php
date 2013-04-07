<?php defined("SYSPATH") or die("No direct script access.") ?>
<form action="<?= url::site("custom_fields") ?>" id="g-quick-search-form" class="g-short-form">
  <ul>
    <li>
      <label for="g-search"><?= t("Search the gallery (with custom fields)") ?></label>
      <input type="text" name="q" id="g-search" class="text" />
    </li>
    <li>
      <input type="submit" value="<?= t("Go")->for_html_attr() ?>" class="submit" />
    </li>
  </ul>
</form>
