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
class custom_fields_theme_Core {
  static function head($theme) {
    return $theme->css("custom_fields.css") . 
           $theme->script("photo_upload_form.js") .
           $theme->script("search_form.js") .
           $theme->script("textLimit.js") .
           $theme->script("jquery.numeric.js");
  }

  static function admin_head($theme) {
    return $theme->script("custom_dialog.js") .
           $theme->script("gallery.panel.js") .
           $theme->script("type_change.js") .
           $theme->css("custom_fields_admin.css");
  }

  static function header_top($theme) {
    if ($theme->page_subtype() != "login") {
      $view = new View("search_link.html");
      return $view->render();
    } else {
      return "";
    }
  }

  // show custom data in thumb view if any
  static function thumb_info($theme, $item) {
    if (!isset($theme->custom_fields_thumb_info)) {
      // TODO: no need for ids, get rid of this
      $child_ids = array();
      foreach ( $theme->children as $child ) {
        $child_ids[] = $child->id;
      }
      // gotta rewind to the beginning!
      $theme->children->rewind();

      if ( !empty($child_ids) ) {
        // get custom metadata for thumbs (indexed by item id)
        $thumb_meta = custom_fields::get_extra_data($theme->children, true, true);
        // gotta rewind to the beginning, again
        $theme->children->rewind();

        $theme->custom_fields_thumb_info = $thumb_meta;
      } else {
        $theme->custom_fields_thumb_info = array();
      }
    }

    if ( empty($theme->custom_fields_thumb_info[$item->id]) ) {
      return "";
    }

    $results = "";
    foreach ($theme->custom_fields_thumb_info[$item->id] as $property_id => $arr_meta) {
      $results .= "<li>" . html::clean($arr_meta['name']) . ": ";
      $bitCount = count($arr_meta['bits']);
    
      $i = 1;
      foreach ($arr_meta['bits'] as $bit_index => $arr_bit) {
        // for month type format data for display
        if ($arr_meta['type'] == 'month') {
          list($month, $year) = custom_fields::convert_month_int( $arr_bit['value'] );
        }

        $results .= $arr_meta['type'] == 'month' ? $month . '/' . $year : html::clean($arr_bit['value']);
        if ($i != $bitCount) {
          $results .= ", ";
        }
        $i++;
      }
      $results .= "</li>";
    }
    return $results;
  }
}