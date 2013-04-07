<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Custom Fields module
 * Copyright (C) 2011 Jozsef Rekedt-Nagy (jozsef.rnagy@site.hu)
 */
class custom_fields_block_Core {
  static function get_site_list() {
    return array("custom_fields" => t("Custom Data of Album"));
  }

  static function get($block_id, $theme) {
    $block = "";
    switch ($block_id) {
      case "custom_fields":

        if ($theme->item) {
          $item = $theme->item;
          $all_custom = custom_fields::get_extra_data($item, true);
          if (count($all_custom) > 0) {
            $block = new Block();
            $block->css_id = "g-custom-fields-block";
            $block->title = t("Custom Data");
            $block->content = new View("custom_fields_sidebar.html");
            $block->content->all_custom = $all_custom;

            // add form for links, to submit via post instead of just get/href link
            $form = new Forge("custom_fields/", "", "post", array("id" => "g-custom-fields-form", "name" => "g-custom-fields-form"));
            $block->content->form = (string)$form;
          }
        }
        break;
    }
    return $block;
  }
}

