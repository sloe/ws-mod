<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Custom Fields module
 * Copyright (C) 2011 Jozsef Rekedt-Nagy (jozsef.rnagy@site.hu)
 */
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2011 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class custom_fields_event_Core {
  static function admin_menu($menu, $theme) {
    $menu->add_after("appearance_menu", Menu::factory("link")
                     ->id("custom_fields")
                     ->label(t("Custom Fields"))
                     ->url(url::site("admin/custom_fields")));

    return $menu;
  }


  static function item_created($item) {
    custom_fields::update($item);
  }

  static function item_updated($original, $new) {
    custom_fields::update($new);
  }

  /**
   * Clean up mapped data of this item
   */
  static function item_deleted($item) {
    db::build()
      ->delete("custom_fields_records")
      ->where("item_id", "=", $item->id)
      ->execute();

    db::build()
      ->delete("custom_fields_freetext_map")
      ->where("item_id", "=", $item->id)
      ->execute();

    db::build()
      ->delete("custom_fields_selection_map")
      ->where("item_id", "=", $item->id)
      ->execute();

    // freetext multilang
    db::build()
      ->delete("custom_fields_freetext_multilang")
      ->where("item_id", "=", $item->id)
      ->execute();
  }

  static function item_related_update($item) {
    custom_fields::update($item);
  }

  static function album_add_form($item, $form) {
    // get list of extra meta fields (not passing item, this shall be filled with empty default values)
    $extra_fields = custom_fields::get_extra_fields(false, false, "album", true);
    custom_fields::add_fields($form, $extra_fields);
    
    // add an extra submit button at the end
	$form->submit("")->value(t("Create"));
  }

  static function album_add_form_completed($item, $form) {
    custom_fields::process_item_form_input($item, $form);
  }

  static function item_edit_form($item, $form) {
    // get list of extra meta fields
    $extra_fields = custom_fields::get_extra_fields($item, false, $item->type);
    custom_fields::add_fields($form, $extra_fields);
  }

  static function item_edit_form_completed($item, $form) {
    custom_fields::process_item_form_input($item, $form);
  }

  static function pre_activate($data) {
    if ($data->module == "search") {
      $data->messages["warn"][] = t("The Search Search module replaces the Search module, you shall not activate both.");
    }
  }

  static function module_change($changes) {
    if (module::is_active("search") || in_array("search", $changes->activate)) {
      site_status::warning(
        t("The Custom Fields module has an advanced Search replacement for the default Search module. <a href=\"%url\">Deactivate the Search module now</a>",
          array("url" => html::mark_clean(url::site("admin/modules")))),
        "custom_fields_replaces_search");
    } else {
      site_status::clear("custom_fields_replaces_search");
    }
  }

  /**
   * Add our custom data into info module sidebar block
   */
  static function info_block_get_metadata($block, $item) {
    // curr metadata
    $info = $block->content->metadata;

    $all_custom = custom_fields::get_extra_data($item, true);

    // add form for links, to submit via post instead of just get/href link
    $form = new Forge("custom_fields/", "", "post", array("id" => "g-custom-fields-form", "name" => "g-custom-fields-form"));

    $arr_pairs = custom_fields::active_filter_2_param_pairs( $all_custom );
    $j = 0;
    foreach ($all_custom as $property_id => $arr_meta) {
      $info_key = "custom_fields_" . $property_id;
      $info[$info_key]["label"] = html::clean($arr_meta['name']) . ":";
      $bitCount = count($arr_meta['bits']);
      
      $i = 1;
      $value = "";
      foreach ( $arr_meta['bits'] as $bit_index => $arr_bit ) {
        // for month type format data for display
        if ( $arr_meta['type'] == 'month' ) {
          list($month, $year) = custom_fields::convert_month_int( $arr_bit['value'] );
        }
        
        if ($arr_meta['searchable']) {
          $value .= "<a href=\"#\" onclick=\"";

          foreach ( $arr_pairs[$property_id][$bit_index] as $index => $arr_param ) {
            $last = $index + 1 == count( $arr_pairs[$property_id][$bit_index] );
            $value .= ($last ? "return " : "") . "custom_fields_submit('" . $arr_param['param'] . "','" 
                   . $arr_param['value'] . "'" . (!$last ? ",false" : "") . ");";
          }
          $value .= "\" >";
        }
        $value .= $arr_meta['type'] == 'month' ? $month . '/' . $year : html::clean($arr_bit['value']);
        if ( $arr_meta['searchable']) {
          $value .= "</a>";
        }

        if ( $i != $bitCount ) {
          $value .= ", ";
        }
        $i++;
      }
      $info[$info_key]["value"] = $value;
      if ( $j == 0 )
      {
        // hack in hidden form, right after the first item
        $info[$info_key]["value"] .= (string)$form;
      }
      $j++;
    }

    $block->content->metadata = $info;
  }

  /* Photo upload time */
  static function add_photos_form($album, $form) {
    $extra_fields = custom_fields::get_extra_fields(false, false, "photo", true);
    custom_fields::add_fields($form, $extra_fields);

    // to get the form validated, all dropdowns has to be sent back with at least a 0 (default/--) value
    foreach ($extra_fields as $property_id => $field) {
      if ($field->type == 'dropdown') {
        $form->add_photos->uploadify->script_data("cf_" . $property_id, "0");
      }
    }

    $group =& $form->inputs["custom_fields"];
    // use serializeArray + some foreach to add to scriptData
    $group->script("")
          ->text("$('input[name^=\"cf_\"],select[name^=\"cf_\"]').change(function () { addToUploadifyScriptData(); });");
  }

  static function add_photos_form_completed($album, $form) {
	custom_fields::process_item_form_input($album, $form);
  }

}
