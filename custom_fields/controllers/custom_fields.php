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
class Custom_fields_Controller extends Controller {
  public function index() {
    $page_size = module::get_var("gallery", "page_size", 9);
    $q = Input::instance()->get("q");
    $arr_advanced = array();
    
    /*** Handle POST begin ***/
    if ( empty($q) ) {
      $q = Input::instance()->post("q");
    }
    // Step1: get list of all the properties/their selections (only searchable ones)
    $arr_fields = custom_fields::get_extra_fields(false, false, false, false, true);
    $form = new Forge("custom_fields/", "", "post", array("id" => "g-custom-fields-form"));
    custom_fields::add_fields($form, $arr_fields);

    // Step2: compare against input, and add to filters..

    // let's assume for now that it is always valid
    // ugly hack to only validate when necessary
    if ( !empty($_POST) ) {
      $form->validate();
    }

    // get input values
    list($arr_freetext, $arr_selection) = custom_fields::grab_form_input_values( $form );

    // build array: $arr_advanced - for search filtering
    foreach( $arr_selection as $property_id => $selection ) {
      if ( !is_array($selection) ) {
        $selection = array($selection);
      }
      foreach ( $selection as $selection_id ) {
        $arr_advanced[] = array( "property_id" => (int)$property_id, "sid" => (int)$selection_id );
      }
    }
    foreach( $arr_freetext as $property_id => $val ) {
      $arr_advanced[] = array( "property_id" => (int)$property_id, "val" => $val );
    }

    /*** Handle POST end ***/

    $page = Input::instance()->get("page", 1);
    // look up in post as well, as a fallback
    if ( $page == 1 ) {
      $page = Input::instance()->post("page", 1);
    }
    // Make sure that the page references a valid offset
    if ($page < 1) {
      $page = 1;
    }
    $offset = ($page - 1) * $page_size;

    list ($count, $result) = custom_fields::search($q, $page_size, $offset, $arr_advanced);
    $max_pages = max(ceil($count / $page_size), 1);

    $template = new Theme_View("page.html", "collection", "custom_fields");
    $template->set_global(array("page" => $page,
                                "max_pages" => $max_pages,
                                "page_size" => $page_size,
                                "children_count" => $count,
                                "form" => (string)$form));

    // generate hidden inputs and paging form based on $arr_paging_params
    if ( $max_pages >= 1 ) {
      $arr_input = custom_fields::grab_form_input_raw( $form );
      $form_hidden = new Forge("custom_fields/", "", "post", array("id" => "g-custom-fields-form-hidden", "name" => "g-custom-fields-form-hidden"));

      foreach ( $arr_input as $fieldName => $value ) {
        $form_hidden->hidden($fieldName)->value($value);
      }
      
      // will need to add 'page' => x into it via paging clicks
      $template->set_global(array("form_hidden" => (string)$form_hidden));
    }

    // Custom pagination, we need onclick POST submit, instead of simple href based
    $pagination = array();
    $pagination['total'] = $count;
    if ($page != 1) {
      $pagination['first_page_url'] = $pagination['previous_page_url'] = true;
    }

    if ($page != $max_pages) {
      $pagination['next_page_url'] = $pagination['last_page_url'] = true;
    }

    $pagination['first_visible_position'] = ($page - 1) * $page_size + 1;
    $pagination['last_visible_position'] = min($page * $page_size, $pagination['total']);
    $template->set_global( $pagination );
    // Custom pagination END

    $template->content = new View("search.html");
    $template->content->items = $result;
    $template->content->q = $q;

    print $template;
  }
}
