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
class Admin_Custom_Fields_Controller extends Admin_Controller {
  public function index() {
    $view = new Admin_View("admin.html");
    $view->page_title = t("Custom Fields");
    $view->content = new View("admin_fields.html");

    // @todo: add this as a config option
    $page_size = module::get_var("custom_fields", "page_size", 20);
    $page = Input::instance()->get("page", "1");
    $builder = db::build();
    $field_count = $builder->from("custom_fields_properties")->count_records();

    $view->content->pager = new Pagination();
    $view->content->pager->initialize(
      array("query_string" => "page",
            "total_items" => $field_count,
            "items_per_page" => $page_size,
            "style" => "classic"));

    // Make sure that the page references a valid offset
    if ($page < 1) {
      url::redirect(url::merge(array("page" => 1)));
    } else if ($page > $view->content->pager->total_pages && $page != 1) {
      url::redirect(url::merge(array("page" => $view->content->pager->total_pages)));
    }

	// grab list of fields @todo: add limit into func
    $view->content->fields = custom_fields::get_extra_fields(false, false, false, false, null, $view->content->pager->sql_offset, $page_size);

    // get list of fields grouped by context (album/photo)
    $view->content->field_groups = $this->_get_fields_context_grouped();

    print $view;
  }


  public function add_field() {
    access::verify_csrf();

    $form = $this->_get_field_add_form_admin();
    try {
      $valid = $form->validate();

      // validate using our custom input validator
      $inputs =& $form->add_field->inputs;
      list($my_valid, $incoming_data, $confirm_dialog) = $this->_validate($inputs);
      $valid = $my_valid && $valid;
    } catch (ORM_Validation_Exception $e) {
      // Translate ORM validation errors into form error messages
      foreach ($e->validation->errors() as $key => $error) {
        $inputs[$key]->add_error($error, 1);
      }
      $valid = false;
    }

    if ($valid) {
      // save new field in DB
      custom_fields::add_field( $incoming_data );
    
      //module::event("field_add_form_admin_completed", $field, $form);
      message::success(t("Created field %field_name", array("field_name" => $inputs["name"]->value)));
      json::reply(array("result" => "success"));
    } else {
      print json::reply(array("result" => "error", "html" => (string)$form));
    }
  }

  public function add_field_form() {
    print $this->_get_field_add_form_admin();
  }

  public function delete_field($id) {
    access::verify_csrf();
    $field = custom_fields::lookup_field($id);
    if (empty($field)) {
      throw new Kohana_404_Exception();
    }

    $form = $this->_get_field_delete_form_admin($field);
    if($form->validate()) {
      $this->_delete_field($id);
    } else {
      json::reply(array("result" => "error", "html" => (string)$form));
    }

    $message = t("Deleted field %field_name", array("field_name" => $field->name));
    log::success("custom_fields", $message);
    message::success($message);
    json::reply(array("result" => "success"));
  }

  public function delete_field_form($id) {
    $field = custom_fields::lookup_field($id);
    if (empty($field)) {
      throw new Kohana_404_Exception();
    }
    $v = new View("admin_fields_delete_field.html");
    $v->field = $field;
    $v->relation_count = $this->_get_relation_count($id);
    $v->form = $this->_get_field_delete_form_admin($field);
    print $v;
  }

  public function edit_field($id) {
    access::verify_csrf();

    $field = custom_fields::lookup_field($id);
    if (empty($field)) {
      throw new Kohana_404_Exception();
    }

    $form = $this->_get_field_edit_form_admin($field);
    try {
      $valid = $form->validate();
      $inputs =& $form->edit_field->inputs;

      // validate using our custom input validator
      list($my_valid, $incoming_data, $confirm_dialog) = $this->_validate($inputs, $field);
      $valid = $my_valid && $valid;

    } catch (ORM_Validation_Exception $e) {
      // Translate ORM validation errors into form error messages
      foreach ($e->validation->errors() as $key => $error) {
        $inputs[$key]->add_error($error, 1);
      }
      $valid = false;
    }

    if ($valid && !isset($confirm_dialog)) {
      //$field->save();
      custom_fields::update_field( $id, $incoming_data );

      //module::event("field_edit_form_admin_completed", $field, $form);
      message::success(t("Changed field %field_name", array("field_name" => $field->name)));
      json::reply(array("result" => "success", "noDialog" => true));
    } elseif ( !isset($confirm_dialog) ) {
      json::reply(array("result" => "error", "html" => (string) $form, "noDialog" => true));
    } else {
      // save non-selection changes, and return with confirmation for them
      custom_fields::update_field( $id, $incoming_data, true );
      json::reply(array("result" => "error", "html" => (string) $confirm_dialog));
    }
  }

  /* Handle confirmation form */
  public function edit_field_confirm($id) {
    access::verify_csrf();

    $field = custom_fields::lookup_field($id);
    if (empty($field)) {
      throw new Kohana_404_Exception();
    }
    
    // must be a field with a type that has related selections
    if ( in_array($field->type, array("month","freetext","integer")) ) {
      throw new Kohana_Exception("BAD REQUEST", null, 400);
    }

    // Note: handle it without forge/form helper as hidden fields are non-deterministic at this point (purely depend on first step, as well as dropdown valueset)
    // Only admin can do this and user/admin can pass step1 anyway, so they can only shoot themselves in the foot if messing with data

    // build array of new selection names to save
    $selections = array();
    foreach (Input::instance()->post("selections", array()) as $selection_id => $value) {
      $value = trim( $value );
      if ( !empty($value) ) {
        $selections[$selection_id] = $value;
      }
    }
    // only freak out if is empty entirely
    if ( empty($selections) ) {
      throw new Kohana_Exception("BAD REQUEST", null, 400);
    }

    // check remapping requests (if any)
    $replacement = array();
    $db = Database::instance();
    foreach (Input::instance()->post("replacement", array()) as $ex_sel_id => $new_sel_id) {
      $value = trim( $value );
      if ( !empty($value) ) {
        $replacement[$ex_sel_id] = $new_sel_id;
        // TODO: save remaps
        // Note: could be done using CASE selection_id..but let's keep it simple for now, likely won't be more than 1-2 queries anyway
        $db->query("UPDATE {custom_fields_selection_map} SET selection_id = $new_sel_id WHERE property_id = $id AND selection_id = $ex_sel_id");
      }
    }
    
    // then save new names and delete ones that are not in incoming set
    custom_fields::override_selections($id, $selections);
    
    message::success(t("Changed field %field_name", array("field_name" => $field->name)));
    json::reply(array("result" => "success", "noDialog" => true));
  }

  public function edit_field_form($id) {
    $field = custom_fields::lookup_field($id);
    if (empty($field)) {
      throw new Kohana_404_Exception();
    }

    $form = $this->_get_field_edit_form_admin($field);
    
    // hack a href link to the end of the form (note: could be moved to a view)
    $form .= "<a href=\"" . url::site("admin/custom_fields/edit_field/$field->property_id") . "\" " .
             "  class=\"g-custom_field_edit-link g-button ui-state-default ui-corner-all ui-icon-left\">" .
             "<span class=\"ui-icon ui-icon-pencil\"></span>" . t("Modify Field") .
             "</a>";

    $csrf = access::csrf_token();
	$orderingUrl = url::site("admin/custom_fields/update_selection_order?csrf={$csrf}__SORTED__&context=selections&property_id=" . $id);

    json::reply(array("html" => (string)$form, "js" => "add_custom_dialog();orderingUrl = '" . $orderingUrl . "'"));
  }

  /* User Form Definitions */
  static function _get_field_edit_form_admin($field) {
    $form = new Forge(
      "admin/custom_fields/edit_field/$field->property_id", "", "post", array("id" => "g-edit-field-form"));
    $group = $form->group("edit_field")->label(t("Edit field"));

	// hidden value, used for constructing data of selection ordering
    $group->hidden("cf_editedfield_id")->value($field->property_id);

    $group->input("name")->label(t("Name"))->id("g-fieldname")->value($field->name)
      ->error_messages("required", t("A name is required"))
      ->error_messages("conflict", t("There is already a field with that name"))
      ->error_messages("length", t("This name is too long"));
    $group->dropdown("type")
      ->label(t("Type"))
      ->options(array("freetext" => "freetext", "dropdown" => "dropdown", "checkbox" => "checkbox", "radio" => "radio", "month" => "month", "integer" => "integer"))
	  ->selected($field->type)
	  ->error_messages("required", t("Type is required"))
	  ->error_messages("type_degradation", t("Type can not be changed from multivalue one single value"))
	  ->error_messages("type_degradation_fixed", t("Type can not be changed from freetext or month to anything else"));
    $group->dropdown("context")
      ->label(t("Context"))
      ->options(array("album" => "Album", "photo" => "Photo", "both" => "Both"))
      ->selected($field->context);
    $group->checkbox("searchable")->label(t("Searchable"))->id("g-admin")->checked($field->searchable);
    $group->checkbox("thumb_view")->label(t("Thumbnail View"))->id("g-admin")->checked($field->thumb_view);
    $group->checkbox("create_input")->label(t("Create Input"))->id("g-admin")->checked($field->create_input);
    // add type specific bits
    $maxOptionId = -1;
    if ( !in_array($field->type, array("month","freetext","integer")) ) {
      foreach ( $field->options as $optionId => $value ) {
        $group->input("selections[$optionId]")
        	  ->ref("$optionId")
        	  ->class("g-draggable-child")
              ->label(t("Options"))
              ->value($value)
              ->error_messages("required", t("List of selections is required for non freetext or month types. (Use semicolon to separate multiple options)"));
        $maxOptionId = max( $optionId, $maxOptionId );
      }
      // add an extra input for new value(s)
      $maxOptionId++;
      $group->input("selections[" . $maxOptionId . "]")
            ->label(t("Options"))
            ->id("max_option")
            ->value("")
            ->error_messages("required", t("List of selections is required for non freetext or month types. (Use semicolon to separate multiple options)"));
    }
    // max length on text
    if ( $field->type == "freetext" ) {
      $group->input("max_length")->label(t("Maximum Length"))->id("g-username")->value($field->max_length);
    }

    // add script to trigger adding selections based on type change(shall not allow removing?)
    $group->script("")
      ->text(
        '$("form").ready(function(){$(\'select[name="type"]\').custom_field_type_change();});');

    // define max selection on load
    $group->script("")
      ->text("var maxSelectionId = " . $maxOptionId  . "; setSortableSelections();");

    // module::event("user_edit_form_admin", $user, $form);
    //$group->submit("")->value(t("Modify field"));
    return $form;
  }

  static function _get_field_add_form_admin() {
    $form = new Forge("admin/custom_fields/add_field", "", "post", array("id" => "g-add-field-form"));
    $group = $form->group("add_field")->label(t("Add field"));

    $group->input("name")->label(t("Name"))->id("g-username")
      ->error_messages("required", t("A name is required"))
      ->error_messages("conflict", t("There is already a field with that name"))
      ->error_messages("length", t("This name is too long"));
    $group->dropdown("type")
      ->label(t("Type"))
      ->options(array("freetext" => "freetext", "dropdown" => "dropdown", "checkbox" => "checkbox", "radio" => "radio", "month" => "month", "integer" => "integer"))
	  ->selected("freetext")
	  ->error_messages("required", t("Type is required"));
    $group->dropdown("context")
      ->label(t("Context"))
      ->options(array("album" => "Album", "photo" => "Photo", "both" => "Both"));
    $group->checkbox("searchable")->label(t("Searchable"))->id("g-admin");
    $group->checkbox("thumb_view")->label(t("Thumbnail View"))->id("g-admin");
    $group->checkbox("create_input")->label(t("Create Input"))->id("g-admin");

    // add type specific bits
    /*
    $group->input("selections")
          ->label(t("Options (Applicable to: dropdown, checkbox, radio. Separate multiple values by semicolon.)"))
          ->id("g-username")
          ->error_messages("required", t("List of selections is required for non freetext or month types. (Use semicolon to separate multiple options)"));
    */

    // max length on text
    $group->input("max_length")->label(t("Maximum Length (applicable to freetext only)"))->id("g-username");

    // add script to trigger adding selections based on type change
    $group->script("")
      ->text(
        '$("form").ready(function(){$(\'select[name="type"]\').custom_field_type_change();});');

    // define max selection on load
    $group->script("")
      ->text('var maxSelectionId = -1; var operation = "add";');

    // module::event("user_add_form_admin", $user, $form);
    $group->submit("")->value(t("Add field"));

    return $form;
  }

  private function _get_field_delete_form_admin($field) {
    $form = new Forge("admin/custom_fields/delete_field/$field->property_id", "", "post",
                      array("id" => "g-delete-field-form"));
    $group = $form->group("delete_field")->label(
      t("Delete field %name?", array("name" => $field->name)));

    $group->submit("")->value(t("Delete"));
    return $form;
  }

  /* Get how many items are related to this field or its selections */
  private function _get_relation_count($field_id, $per_selection=false) {
    if ( !$per_selection ) {
      $rel_count = db::build()
        ->from("custom_fields_properties")
        ->join("custom_fields_freetext_map", "custom_fields_freetext_map.property_id", "custom_fields_properties.id", "left")
        ->join("custom_fields_selection_map", "custom_fields_selection_map.property_id", "custom_fields_properties.id", "left")
        ->and_open()
        ->where("custom_fields_freetext_map.property_id", "=", $field_id)
        ->or_where("custom_fields_selection_map.property_id", "=", $field_id)
        ->close()
        ->count_records();
    } else {
      $data = db::build()
        ->select("custom_fields_selection.selection_id")
        ->select(array("c" => "COUNT(\"item_id\")"))
        ->from("custom_fields_selection")
        ->join("custom_fields_selection_map", "custom_fields_selection_map.selection_id", "custom_fields_selection.selection_id", "left")
        ->where("custom_fields_selection.property_id", "=", $field_id)
        ->group_by("custom_fields_selection.selection_id")
        ->execute();
      
      $rel_count = array();
      // index by selection id
      foreach ( $data as $row ) {
        $rel_count[$row->selection_id] = $row->c;
      }
    }
    return $rel_count;
  }

  /* Save newly added fields, then make sure confirmation lists newly added ones and these with proper selection id */
  private function _construct_confirmation_form($field, &$incoming_selections, $arr_to_confirm) {
    // save extra selections
    $maxSavedKey = max( array_keys($field->options) );
    $arr_new_sels = array();
    foreach ( $incoming_selections as $selection_id => $name ) {
      // build new array and unset in input array, as the related selection_id *might* not be the one that DB will assign (gets readded, see below)
      if ( $selection_id > $maxSavedKey ) {
        $arr_new_sels[] = $name;
        unset( $incoming_selections[$selection_id] );
      }
    }
    if ( !empty($arr_new_sels) ) {
      custom_fields::add_selections($id, $arr_new_sels);
            
      // mod $incoming_selections to push in real selection ids from database for new selection values
      // Note: can't just override it with db data because value updates (of existing selections) are not getting saved here yet,
      // due to possible user mistakes that are getting cancelled in the dialogue (adding new ones on the other hand can't hurt existing links)
      $updated_field = custom_fields::lookup_field($id);

      foreach( $updated_field->options as $selection_id => $value ) {
        // do not include those that are getting deleted on purpose
        if ( $selection_id > $maxSavedKey ) {
          $incoming_selections[$selection_id] = $value;
        }
      }
    }
        
    $v = new View("admin_fields_edit_field_confirmation.html");
    $v->field = $field;
    $v->form = $this->_get_field_edit_form_confirmation_admin($field, $arr_to_confirm, $incoming_selections);
    
    return $v;
  }


  /* Get field edit confirmation dialogue form */
  static function _get_field_edit_form_confirmation_admin($field, $selections_confirmable, $arr_new_selections) {
    $form = new Forge(
      "admin/custom_fields/edit_field_confirm/$field->property_id", "", "post", array("id" => "g-edit-field-form"));
    $group = $form->group("edit_field_confirm")->label(t("Edit field Confirmation"));
    foreach ( $selections_confirmable as $selection_id => $sel_data ) {
      // do not add selections with 0 relation (does not matter from data relation pov)
      if ( $sel_data["rel_count"] ) {
        $group->dropdown("replacement[$selection_id]")
          ->label(t($sel_data["name"] . " ($sel_data[rel_count] relations)"))
          ->options(array_merge(array(0 => "--"),$arr_new_selections))
    	  ->selected('');
  	  }
    }
    // add new selections (added in step1) as hidden fields
    foreach ( $arr_new_selections as $selection_id => $name ) {
      $group->hidden("selections[$selection_id]")->value($name);
    }

    $group->submit("")->value(t("Confirm Changes"));

    return $form;
  }

  /* Get fields grouped by context */
  private function _get_fields_context_grouped() {
    $db = Database::instance();
    $query =
      "SELECT {custom_fields_properties}.id, {custom_fields_properties}.name, {custom_fields_properties}.context " .
      "FROM {custom_fields_properties} ORDER BY `order` ASC, name ASC";

    $prop_list = $db->query($query);
    $groups = array();
    foreach ( $prop_list as $property ) {
      $groups['all'][] = $property;
      switch ( $property->context ) {
        case 'album':
          $groups['album'][] = $property;
          break;

        case 'photo':
          $groups['photo'][] = $property;
          break;

        case 'both':
          $groups['photo'][] = $groups['album'][] = $property;
          break;
      }
    }
    return $groups;
  }

  /* Handle updating order of fields */
  public function update_field_order() {
    access::verify_csrf();

    //$available_blocks = block_manager::get_available_site_blocks();
    $i = 1;
    foreach (Input::instance()->get("block", array()) as $block_id) {
      $block_order[intval($block_id)] = $i;
      $i++;
    }
    // will allow for 'all' only
    // $context = Input::instance()->get("context");
    $this->_set_field_order($block_order);

    $result = array("result" => "success");
    $blocks = $this->_get_fields_context_grouped();
    
    foreach ( $blocks as $context => $group_fields ) {
      $v = new View("admin_context_block_items.html");
      $v->group = $group_fields;
      $v->context = $context;
      $result[$context] = $v->render();
    }

    $message = t("Updated field order");
    $result["message"] = (string) $message;
    json::reply($result);
  }

  /* Direct method of saving field order */
  private function _set_field_order($block_order) {
    $db = Database::instance();

    // normally only a single field gets reordered (as order saving is triggered after every dragdrop)
    // so let's build on top of current order in DB of _all_ fields (no context filter)
    $query = "SELECT {custom_fields_properties}.id, {custom_fields_properties}.order " .
             "FROM {custom_fields_properties}";
    $curr_orders = $db->query($query);
    $arr_primary = $arr_secondary = array();
    foreach ( $curr_orders as $field ) {
      $arr_primary[$field->id] = isset($block_order[$field->id]) ? $block_order[$field->id] : 0;
    }

    // resort
    asort( $arr_primary );
    $case_list = "";
    foreach ( $arr_primary as $id => $order ) {
      $case_list .= " WHEN $id THEN $order ";
    }

    // update order
    $query =
      "UPDATE {custom_fields_properties} " .
      "SET `order` = CASE id " .
      "$case_list " .
      "ELSE `order` END";

    $db->query($query);
  }


  /* Handle updating order of fields */
  public function update_selection_order() {
    access::verify_csrf();

    $i = 1;
    foreach (Input::instance()->get("block", array()) as $block_id) {
      $block_order[intval($block_id)] = $i;
      $i++;
    }

    $id = Input::instance()->get("property_id");
	$id = intval( $id );
    $this->_set_selection_order($id, $block_order);

    $result = array("result" => "success");
    /*
    $blocks = $this->_get_fields_context_grouped();
    foreach ( $blocks as $context => $group_fields ) {
      $v = new View("admin_context_block_items.html");
      $v->group = $group_fields;
      $v->context = $context;
      $result[$context] = $v->render();
    }
	*/

    $message = t("Updated selection order");
    $result["message"] = (string) $message;
    json::reply($result);
  }

  /* Direct method of saving field order */
  private function _set_selection_order($id, $block_order) {
    $db = Database::instance();
    // normally only a single field gets reordered (as order saving is triggered after every dragdrop)
    $query = "SELECT {custom_fields_selection}.selection_id, {custom_fields_selection}.order " .
             "FROM {custom_fields_selection} " .
             "WHERE property_id = " . $id;
    $curr_orders = $db->query($query);
    $arr_primary = $arr_secondary = array();
    foreach ( $curr_orders as $selection ) {
      $arr_primary[$selection->selection_id] = isset($block_order[$selection->selection_id]) ? $block_order[$selection->selection_id] : 0;
    }

    // resort
    asort( $arr_primary );
    $case_list = "";
    foreach ( $arr_primary as $selection_id => $order ) {
      $case_list .= " WHEN $selection_id THEN $order ";
    }

    // update order
    $query =
      "UPDATE {custom_fields_selection} " .
      "SET `order` = CASE selection_id " .
      "$case_list " .
      "ELSE `order` END " .
      "WHERE property_id = " . $id;

    $db->query($query);
  }


  /* Pseudo validation, not using a model (yet) */
  private function _validate(&$inputs, $field=false) {
    // Revisit: could use a model here
    $incoming_data = array();
    $valid = true;

    // edit specific
    if ( $field ) {
      $need_confirmation = false;
    }

    // name
    $incoming_data["name"] = $inputs["name"]->value;
    if ( empty($incoming_data["name"]) ) {
      $inputs["name"]->add_error("required", 1);
      $valid = false;
    }

    // dropdowns (type, context)
    foreach ( array("type","context") as $dropdown ) {
      $incoming_data[$dropdown] = $inputs[$dropdown]->selected;
    }

    // type can not be changed from multivalue one to singlevalue
    if ( $field ) {
      if ( !in_array($field->type, array("month","freetext","integer")) && in_array($incoming_data["type"], array("month","freetext","integer")) ) {
        $inputs["type"]->add_error("type_degradation", 1);
        $valid = false;
      } elseif ( in_array($field->type, array("month","freetext")) && $incoming_data["type"] != $field->type ) {
        $inputs["type"]->add_error("type_degradation_fixed", 1);
        $valid = false;
      }
    }

    // value if appropriate
    if ( !in_array($incoming_data["type"], array('month','freetext',"integer")) ) {
      $incoming_data["selections"] = array();
      foreach (Input::instance()->post("selections", array()) as $selection_id => $value) {
        $value = trim( $value );
        if ( !empty($value) ) {
          $incoming_data["selections"][$selection_id] = $value;
        }
      }

      // check if _any_ of the selections is getting deleted (must show confirmation with dropdown/delete)
      if ( $field ) {
        // get current mapping count for all saved selections
        $rel_count = $this->_get_relation_count($field->property_id, true);

        $arr_to_confirm = array();
        foreach ( $field->options as $selection_id => $name ) {
          if ( empty($incoming_data["selections"][$selection_id]) && $rel_count[$selection_id] ) {
            $arr_to_confirm[$selection_id] = array("name" => $name, "rel_count" => $rel_count[$selection_id]);
            $need_confirmation = true;
          }
        }
  
        // build dialog form if $need_confirmation and save extra selections
        if ( $need_confirmation ) {
          $v = $this->_construct_confirmation_form($field, $incoming_data["selections"], $arr_to_confirm);
        }
      }

      if ( empty($incoming_data["selections"]) ) {
        $inputs["selections"]->add_error("required", 1);
        $valid = false;
      }
    }
    elseif ( $field && $field->type == 'freetext' ) {
      // max field length
      $incoming_data["max_length"] = $inputs["max_length"]->value; 
    }

    // checkboxes
    foreach ( array("searchable","thumb_view", "create_input") as $checkbox ) {
      $incoming_data[$checkbox] = $inputs[$checkbox]->checked;
    }

    // with modal, would be:
    //$field->validate();
    return array($valid, $incoming_data, isset($v) ? $v : null);
  }

  /* Delete a field with all its relations */
  private function _delete_field( $id )
  {
    $db = Database::instance();

    // delete field/property itself
    $query = "DELETE FROM {custom_fields_properties} " .
      "WHERE id = $id";
    $db->query($query);

    // delete selections if any
    $query = "DELETE FROM {custom_fields_selection} " .
      "WHERE property_id = $id";
    $db->query($query);

    // delete multilang values
    $query = "DELETE FROM {custom_fields_properties_multilang} " .
      "WHERE property_id = $id";
    $db->query($query);

    $query = "DELETE FROM {custom_fields_freetext_multilang} " .
      "WHERE property_id = $id";
    $db->query($query);
    
    $query = "DELETE FROM {custom_fields_selection_multilang} " .
      "WHERE property_id = $id";
    $db->query($query);
    
    // trigger setting search index dirty flag to 1 on items related to this field
    $query = "UPDATE {custom_fields_records} r LEFT JOIN {custom_fields_selection_map} sm ON sm.property_id = $id AND sm.item_id = r.item_id " .
      "LEFT JOIN {custom_fields_freetext_map} fm ON fm.property_id = $id AND fm.item_id = r.item_id " .
      "SET r.dirty = 1 " .
      "WHERE sm.property_id IS NOT NULL OR fm.property_id IS NOT NULL";
    $db->query($query);

    // delete mappings
    $query = "DELETE FROM {custom_fields_selection_map} " .
      "WHERE property_id = $id";
    $db->query($query);

    $query = "DELETE FROM {custom_fields_freetext_map} " .
      "WHERE property_id = $id";
    $db->query($query);

    // raise index out of date warning if necessary
    custom_fields::check_index();
  }
}
