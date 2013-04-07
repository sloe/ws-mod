<?php defined("SYSPATH") or die("No direct script access.") ?>
<? // @todo Set hover on AlbumGrid list items ?>
<form action="<?= url::site("/custom_fields") ?>" method="post" id="g-search-form" class="g-short-form">
  <fieldset>
    <legend>
      <?= t("Search") ?>
    </legend>
    <ul>
      <li>
        <label for="q"><?= t("Search the gallery") ?></label>
        <input name="q" id="q" type="text" value="<?= html::clean_attribute($q) ?>" class="text" />
      </li>
      <li id="top-search-submit">
        <input type="submit" value="<?= t("Search")->for_html_attr() ?>" class="submit" />
      </li>
      <li id="custom_fields_form">
        <?= $form ?>
      </li>
      <li>
        <input type="submit" value="<?= t("Search")->for_html_attr() ?>" class="submit-mine" />
      </li>
    </ul>
  </fieldset>
</form>

<?= $form_hidden ?>

<div id="g-search-results">
  <h1><?= t("Search results") ?></h1>

  <? if (count($items)): ?>
  <ul id="g-album-grid" class="ui-helper-clearfix">
    <? foreach ($items as $item): ?>
    <? $item_class = $item->is_album() ? "g-album" : "g-photo" ?>
    <li class="g-item <?= $item_class ?>">
      <a href="<?= $item->url() ?>">
        <?= $item->thumb_img() ?>
        <p>
          <?= html::purify(text::limit_chars($item->title, 32, "…")) ?>
         </p>
         <div>
          <?= nl2br(html::purify(text::limit_chars($item->description, 64, "…"))) ?>
        </div>
      </a>
    </li>
    <? endforeach ?>
  </ul>


<ul class="g-paginator ui-helper-clearfix">
  <li class="g-first">
  <? if ($page_type == "collection"): ?>
    <? if (isset($first_page_url)): ?>
      <a href="#" onclick="return custom_fields_paging(1)" class="g-button ui-icon-left ui-state-default ui-corner-all">
        <span class="ui-icon ui-icon-seek-first"></span><?= t("First") ?></a>
    <? else: ?>
      <a class="g-button ui-icon-left ui-state-disabled ui-corner-all">
        <span class="ui-icon ui-icon-seek-first"></span><?= t("First") ?></a>
    <? endif ?>
  <? endif ?>

  <? if (isset($previous_page_url)): ?>
    <a href="#" onclick="return custom_fields_paging(<?=$page-1?>)" class="g-button ui-icon-left ui-state-default ui-corner-all">
      <span class="ui-icon ui-icon-seek-prev"></span><?= t("Previous") ?></a>
  <? else: ?>
    <a class="g-button ui-icon-left ui-state-disabled ui-corner-all">
      <span class="ui-icon ui-icon-seek-prev"></span><?= t("Previous") ?></a>
  <? endif ?>
  </li>

  <li class="g-info">
    <? if ($total): ?>
      <? if ($page_type == "collection"): ?>
        <?= /* @todo This message isn't easily localizable */
            t2("Items %from_number of %count",
               "Items %from_number - %to_number of %count",
               $total,
               array("from_number" => $first_visible_position,
                     "to_number" => $last_visible_position,
                     "count" => $total)) ?>
      <? else: ?>
        <?= t("%position of %total", array("position" => $position, "total" => $total)) ?>
      <? endif ?>
    <? else: ?>
      <?= t("No photos") ?>
    <? endif ?>
  </li>

  <li class="g-text-right">
  <? if (isset($next_page_url)): ?>
    <a href="#" onclick="return custom_fields_paging(<?=$page+1?>)" class="g-button ui-icon-right ui-state-default ui-corner-all">
      <span class="ui-icon ui-icon-seek-next"></span><?= t("Next") ?></a>
  <? else: ?>
    <a class="g-button ui-state-disabled ui-icon-right ui-corner-all">
      <span class="ui-icon ui-icon-seek-next"></span><?= t("Next") ?></a>
  <? endif ?>

  <? if ($page_type == "collection"): ?>
    <? if (isset($last_page_url)): ?>
      <a href="#" onclick="return custom_fields_paging(<?=$max_pages?>)" class="g-button ui-icon-right ui-state-default ui-corner-all">
        <span class="ui-icon ui-icon-seek-end"></span><?= t("Last") ?></a>
    <? else: ?>
      <a class="g-button ui-state-disabled ui-icon-right ui-corner-all">
        <span class="ui-icon ui-icon-seek-end"></span><?= t("Last") ?></a>
    <? endif ?>
  <? endif ?>
  </li>
</ul>


  <? else: ?>
  <p>
    <?= t("No results found for the specified filters") ?>
  </p>

  <? endif; ?>
</div>
