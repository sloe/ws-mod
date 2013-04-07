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
class custom_fields_Core {
  static function search($q, $limit, $offset, $arr_custom_filter) {
    $db = Database::instance();
    $q = $db->escape($q);

    if (!identity::active_user()->admin) {
      foreach (identity::group_ids_for_active_user() as $id) {
        $fields[] = "`view_$id` = TRUE"; // access::ALLOW
      }
      $access_sql = "AND (" . join(" OR ", $fields) . ")";
    } else {
      $access_sql = "";
    }

    // Sanitizing all the input, ints (like $property_id) are sanitized casted forcibly in the caller. 
    // Revisit: extra sanitization of ids against existing property_id and selection ids using getextradata?

    // build the custom filter for single/multi AND filtering
    $advanced_sql = "";
    $arr_advanced = array();
    foreach ( $arr_custom_filter as $arr_filter ) {
      // selection based
      if ( !empty($arr_filter["sid"]) ) {
        $alias = "sm" . $arr_filter['property_id'] . "_" . $arr_filter["sid"];
        $arr_advanced[] = "JOIN {custom_fields_selection_map} $alias " .
                          "  ON ($alias.property_id = $arr_filter[property_id] AND $alias.selection_id = $arr_filter[sid] AND $alias.item_id = {custom_fields_records}.item_id) ";
      } 
      // freetext based
      else {
        $val = $db->escape($arr_filter["val"]);
        $alias = "fm" . $arr_filter["property_id"];
        $arr_advanced[] = "JOIN {custom_fields_freetext_map} $alias " .
                          " ON ($alias.property_id = $arr_filter[property_id] AND $alias.value LIKE '%$val%' AND $alias.item_id = {custom_fields_records}.item_id) ";
      }
    }
    $advanced_sql = implode(" ", $arr_advanced);

    $query =
      "SELECT SQL_CALC_FOUND_ROWS {items}.*, " .
      "  MATCH({custom_fields_records}.`data`) AGAINST ('$q') AS `score` " .
      "FROM {items} JOIN {custom_fields_records} ON ({items}.`id` = {custom_fields_records}.`item_id`) " .
      $advanced_sql .
      "WHERE 1 = 1 " .
      (!empty($q) ? " AND MATCH({custom_fields_records}.`data`) AGAINST ('$q' IN BOOLEAN MODE) " : "") .
      $access_sql .
      "ORDER BY `score` DESC " .
      "LIMIT $limit OFFSET $offset";
    $data = $db->query($query);
    $count = $db->query("SELECT FOUND_ROWS() as c")->current()->c;

    return array($count, new ORM_Iterator(ORM::factory("item"), $data));
  }

  /**
   * @return string An error message suitable for inclusion in the task log
   */
  static function check_index($force_warning=false) {
    if (!$force_warning) {
      list ($remaining) = custom_fields::stats();
    }
    if ($force_warning || $remaining) {
      site_status::warning(
        t('Your Custom Fields index needs to be updated.  <a href="%url" class="g-dialog-link">Fix this now</a>',
          array("url" => html::mark_clean(url::site("admin/maintenance/start/custom_fields_task::update_index?csrf=__CSRF__")))),
        "custom_fields_index_out_of_date");
    }
  }

  static function update($item) {
    $data = new ArrayObject();
    $record = ORM::factory("custom_fields_record")->where("item_id", "=", $item->id)->find();
    if (!$record->loaded()) {
      $record->item_id = $item->id;
    }

    $item = $record->item();
    module::event("item_index_data", $item, $data);
    
    // add in our custom values at this point
    $extra_data = custom_fields::get_extra_data($item);
    $data = array_merge((array)$data, $extra_data);
    
    $record->data = join(",", (array)$data);
    $record->dirty = 0;
    $record->save();
  }

  static function stats() {
    $remaining = db::build()
      ->from("items")
      ->join("custom_fields_records", "items.id", "custom_fields_records.item_id", "left")
      ->and_open()
      ->where("custom_fields_records.item_id", "IS", null)
      ->or_where("custom_fields_records.dirty", "=", 1)
      ->close()
      ->count_records();

    $total = ORM::factory("item")->count_all();
    $percent = round(100 * ($total - $remaining) / $total);

    return array($remaining, $total, $percent);
  }

  /**
   * Get field(s)
   */
  static function get_extra_fields($item=false, $property_id=false, $itemType=false, $creation_time=false, $searchable_only=null, $offset=0, $count=false) {
    $db = Database::instance();
    $item_id = $item ? $item->id : 0;

    // @revisit: paging is a bit complex with this type of query (could be achieved way simpler by grabbing list of property_ids first then joining other data)
    $query = ($count ? "SELECT * FROM (SELECT *, @curr_pos := if (@curr_prop = property_id, @curr_pos, @curr_pos+1) as position, @curr_prop := property_id FROM (" : "") .
      "SELECT {custom_fields_properties}.id as property_id, {custom_fields_properties}.name, {custom_fields_properties}.type, {custom_fields_properties}.context, {custom_fields_properties}.searchable, " .
	  " {custom_fields_properties}.thumb_view, {custom_fields_properties}.create_input, {custom_fields_properties}.max_length, {custom_fields_selection}.selection_id, {custom_fields_selection}.value, " .
      "	{custom_fields_freetext_map}.value as savedFreetext, {custom_fields_selection_map}.selection_id as savedSelection " . 
      "FROM " . (!$count ? "" : "(SELECT @curr_pos := 0, @curr_prop:=0) init, ") . " {custom_fields_properties} " . 
      "LEFT JOIN {custom_fields_selection} " .
      "	ON ({custom_fields_selection}.property_id = {custom_fields_properties}.id) " .
      "LEFT JOIN {custom_fields_freetext_map} " .
      "	ON ({custom_fields_freetext_map}.property_id = {custom_fields_properties}.id " .
      "		AND {custom_fields_freetext_map}.item_id = $item_id) " .
      "LEFT JOIN {custom_fields_selection_map} " .
      "	ON ({custom_fields_selection_map}.property_id = {custom_fields_properties}.id " .
      "		AND {custom_fields_selection_map}.selection_id = {custom_fields_selection}.selection_id " .
	  "    	AND {custom_fields_selection_map}.item_id = $item_id) ";

	// filter for propery if set
	$arr_filter = array();
    if ( $property_id ) {
    	$arr_filter[] = "{custom_fields_properties}.id = " . intval($property_id);
    }

    // filter for item type if set
    if ( $itemType ) {
    	$arr_filter[] = "{custom_fields_properties}.context IN ('both', '" . $db->escape($itemType) . "')";
    }

    // filter for creation_time (only) bit
    if ( $creation_time ) {
    	$arr_filter[] = "{custom_fields_properties}.create_input = 1";
    }

    // filter for searchable bit
    if ( isset($searchable_only) ) {
    	$arr_filter[] = "{custom_fields_properties}.searchable = 1";
    }

    // collect & add filters
    if ( !empty($arr_filter) ) {
      $query .= " WHERE " . implode(' AND ', $arr_filter);
    }
	 
    $query .= " ORDER BY {custom_fields_properties}.order ASC, property_id ASC, {custom_fields_selection}.order ASC, selection_id ASC";

    // add limit if any
    if ( $count ) {
      $query .= ") innerTbl ) positioned WHERE position BETWEEN $offset+1 AND $offset+$count";
    }
    $field_list = $db->query($query);
    
    // group multiselections into a single entry with options in array
    $extra_fields = array();
    foreach ( $field_list as $field ) {
      if ( in_array($field->type, array('dropdown','checkbox','radio')) ) {
        if ( !isset($extra_fields[$field->property_id]) ) {
          $extra_fields[$field->property_id] = $field;
          $extra_fields[$field->property_id]->options = array();
          $extra_fields[$field->property_id]->selected = array();
        }
        $extra_fields[$field->property_id]->options[$field->selection_id] = $field->value;

        // add in saved selection if its selected/positive
        if ( $val = $field->savedSelection ) {
          $extra_fields[$field->property_id]->selected[$field->selection_id] = true;
        }
      }
      else {
        $extra_fields[$field->property_id] = $field;
      }
    }
    return $extra_fields;
  }

  /**
   * Delete all custom data associated with an item
   */
  static function clear_all($item) {
    // freetext
    db::build()
      ->delete("custom_fields_freetext_map")
      ->where("item_id", "=", $item->id)
      ->execute();

    // Note: multilang values are not getting flushed automatically, user shall edit them on their own
    /*
    // freetext multilang
    db::build()
      ->delete("custom_fields_freetext_multilang")
      ->where("item_id", "=", $item->id)
      ->execute();
    */

    // selections
    db::build()
      ->delete("custom_fields_selection_map")
      ->where("item_id", "=", $item->id)
      ->execute();

    // no need to delete delete from search records, data is getting overriden there
    /*
    db::build()
      ->delete("custom_fields_records")
      ->where("item_id", "=", $item->id)
      ->execute();
    */
  }

  /**
   * Get mapped data for custom/extra fields (used purely for indexing)
   * 
   * @param mixed item or array of items
   * @param bool wether data is requested for linking
   * @param bool wether filtering for thumb_view=1
   */
  static function get_extra_data($items, $for_linking=false, $thumb_view=false) {
    $db = Database::instance();
    
    // can't use stuff like !is_array()
    if (get_class($items) == "Item_Model") {
      $items = array($items);
    }
    $arr_item_id = array();
    $item_count = count($items);
    foreach ( $items as $item ) {
      $arr_item_id[] = $item->id;
    }
    
    $where_filter = " IN (" . implode(",",$arr_item_id) . ") ";
    
    $thumb_filter = $thumb_view ? " JOIN {custom_fields_properties} p2 ON p2.id = s.property_id AND p2.thumb_view = 1 " : "";
    
    $query =
      "SELECT item_id, s.property_id, 0 as selection_id, s.value " .
      "FROM {custom_fields_freetext_map} s $thumb_filter" .
      " WHERE s.item_id $where_filter " .
      "UNION " .
      "SELECT item_id, s.property_id, s.selection_id, s.value " .
      "FROM {custom_fields_selection_map} sm " .
      " JOIN {custom_fields_selection} s" .
      "    ON s.property_id = sm.property_id " .
      "      AND s.selection_id = sm.selection_id " .
      "  $thumb_filter " .
      " WHERE sm.item_id $where_filter";
      
    if ( $for_linking ) {
      $query = "SELECT innerQ.property_id, innerQ.selection_id, innerQ.value, innerQ.item_id, p.name, p.type, p.searchable "  .
               "FROM {custom_fields_properties} p JOIN ( " .
               $query .
               ") innerQ ON p.id = innerQ.property_id " .
               "ORDER BY property_id ASC, selection_id ASC";
    }

    $data_list = $db->query($query);

    // group multiselections into a single entry with options in array
    $extra_data = array();
    foreach ($data_list as $data) {
      if (!$for_linking) {
        $extra_data[] = $data->value;
      } else {
        if ($item_count == 1 && !$thumb_view) {
          $container =& $extra_data[$data->property_id];
        } else {
          $container =& $extra_data[$data->item_id][$data->property_id];
        }
      
        // isset/valset again: nm
        $container['name'] = $data->name;
        $container['type'] = $data->type;
        $container['searchable'] = $data->searchable;
        $container['bits'][] = array( 'selection_id' => $data->selection_id, 'value' => $data->value );
      }
    }
    return $extra_data;
  }

  /**
   * Add mapping of freetext input to DB
   */
  static function add_freetext($item, $arr_freetext) {
    $db = Database::instance();
    $insert = array();
    foreach( $arr_freetext as $property_id => $value ) {
	  $insert[] = "(" . $item->id . "," . $property_id . ",'" . $db->escape(trim($value)) . "')";
	}

    $query =
      "INSERT INTO {custom_fields_freetext_map} (item_id, property_id, value) " .
      "VALUES " . implode(',', $insert);
    $db->query($query);

    // insert/update multilang variant. Note: is not updating/flushing languages other than active
    $locale = custom_fields::get_active_local();
    $query =
      "INSERT INTO {custom_fields_freetext_multilang} (property_id, item_id, locale, value) " .
      "VALUES($item->id, $item->id, '" . $db->escape($locale) . "','" . $db->escape(trim($value)) . "') " .
      "ON DUPLICATE KEY UPDATE value=VALUES(value)";
    $db->query($query);
  }

  /**
   * Add mapping of selection based input to DB
   */
  static function add_selection($item, $arr_selection) {
    $db = Database::instance();
    $insert = array();
    foreach( $arr_selection as $property_id => $selection ) {
      // any value could be multidimensional in here (checkboxes)
      if ( !is_array($selection) ) {
        $selection = array( $selection );
      }
      foreach ( $selection as $selection_id ) {
    	$insert[] = "(" . $item->id . "," . $property_id . "," . $selection_id . ")";
	  }
	}

    $query =
      "INSERT INTO {custom_fields_selection_map} (item_id, property_id, selection_id) " .
      "VALUES " . implode(',', $insert);
    $db->query($query);
  }

  /**
   * Convert month into into month, year pair
   */
  static function convert_month_int($month_int) {
    $year = floor($month_int / 12);
    $month = $month_int % 12;
    return array( $month, $year );
  }

  /**
   * Add custom fields into the form
   */
  static function add_fields(&$form, $extra_fields) {
    // get values of current maps
    $group = $form->group("custom_fields")
                  ->label(t("Custom Fields Input"));
    foreach ( $extra_fields as $field ) {
      switch ( $field->type ) {
        case 'freetext':
          $remaining = $field->max_length ? $field->max_length - strlen($field->savedFreetext) : 0;
          $label = $field->name . (!$field->max_length ? "" 
                                   : " (" . t("max %max_length characters", array("max_length" => $field->max_length)) 
                                    . " - <span>" . $remaining . "</span> " . t("left") . ")" );

          $input = $group->input("cf_" . $field->property_id, array("extraType" => "freetext"))
                         ->label($label)
                         ->value($field->savedFreetext);

          // add max_length error if applicable
          if ( $field->max_length ) {
            $input->error_messages("length",
                                     t("The value must be less than %max_length characters",
                                       array("max_length" => $field->max_length)));
            $form->script("")
                 ->text("$('input[name=\"cf_" . $field->property_id . "\"]').textLimit(" . $field->max_length . ", function(length, limit) {
                          $(this).parent().find('span').text( limit - length );
                        });");
          }
          break;

        case 'integer':
          $input = $group->input("cf_" . $field->property_id)
                         ->label($field->name . " " . "(" . t("Integer") . ")")
                         ->value($field->savedFreetext);
          $form->script("")
                 ->text("$('input[name=\"cf_" . $field->property_id . "\"]').numeric(false,
                         function(){ alert('" . t("Integers only") . "'); })");
          break;
          
        case 'dropdown':
          $selected = !empty($field->selected) ? key($field->selected) : 0;
          // merging in a default empty value into options
          $group->dropdown("cf_" . $field->property_id, array("extraType" => "dropdown"))
              ->label(t($field->name))
              ->options(array_merge(array('--'),$field->options))
              ->selected($selected);
          break;
        
        case 'month':
          // TODO: gotta add 2 inputs in a group for this, month/year dropdowns just a list of months in the last years since 2010
          $month = $group->group("cf_" . $field->property_id, "month")
                         ->label(t($field->name));

          // setting month array keys=values, doing in a funnny way :)
          $arrMonths = array(0 => "--", 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12);
          
          // restore saved value
          $monthInt = $field->savedFreetext;
          if ( $monthInt ) {
            list($savedMonth, $savedYear) = custom_fields::convert_month_int( $monthInt );
          } else {
            $savedYear = $savedMonth = 0;
          }
          
          $month->dropdown("cf_" . $field->property_id . "_month")
                ->label(t("Month"))
                ->options($arrMonths)
                ->selected($savedMonth);

          // add def
          $arrYears[0] = "--";
          $arrYears = array_merge($arrYears, range(2010, date("Y")));
          $arrYears = array_flip( $arrYears );
          foreach ( $arrYears as $key => &$val ) {
            $val = $key;
          }

          $month->dropdown("cf_" . $field->property_id . "_year")
                ->label(t("Year"))
                ->options($arrYears)
                ->selected($savedYear);
          break;
        
        case 'checkbox':
          $cb = $group->group("cf_" . $field->property_id, "checkbox_group")
                      ->label(t($field->name));

          foreach ( $field->options as $id => $option ) {
            $checked = isset($field->selected[$id]);
            $cb->checkbox("cf_" . $field->property_id . "_" . $id, array("optionId" => "vzze"))
                  ->label(t($option))
                  ->checked($checked);
          }
          break;
        
        case 'radio':
          $radio = $group->group("cf_" . $field->property_id, "radio_group")
                         ->label($field->name);

          // using custom Form_Radio class
          $selected = !empty($field->selected) ? key($field->selected) : 0;
          $radio->radio("cf_" . $field->property_id)
                ->options($field->options)
                ->default($selected);
          break;
      }
    }
  }

  /**
   * Parse inputted filters from form
   */
  static function grab_form_input_values($form) {
    $extra_fields = custom_fields::get_extra_fields();

    // damn vars can't be isset/empty checked without assigning to local vars first, being protected
    $arr_freetext = $arr_selection = array();

    foreach ( $form->custom_fields->inputs as $fieldName => $fieldData ) {
      $tmp = explode( '_', $fieldName );
      $property_id = array_pop( $tmp );
      
      // check if its getting set atm
      if ( !isset($extra_fields[$property_id]) ) {
        continue;
      }
    	
      // switch based on property type
      switch ( $extra_fields[$property_id]->type ) {
      	case 'freetext':
    	  $value = trim($fieldData->value);
	   	  if ( !empty($value) ) {
      	    // instead of  breaking the submit, let's just trim values to max_length if specified and raise a message on saving
	    	if ( $extra_fields[$property_id]->max_length && strlen($value) > $extra_fields[$property_id]->max_length ) {
    		  $value = substr($value, 0, $extra_fields[$property_id]->max_length);
    		  message::warning($extra_fields[$property_id]->name . " " 
    		    . t("got trimmed to %max_length characters", array("max_length" => $extra_fields[$property_id]->max_length)));
    		}
    		$arr_freetext[$property_id] = $value;
    	  }
    	  break;

  	    case 'integer':
    	  $value = $fieldData->value;
	   	  if ( !empty($value) || $value === "0" ) {
	        // instead of  breaking the submit, forcibly cast the value to int and raise a message on saving
	        if ( intval($value) != $value ) {
  	    	  $value = intval($value);
	    	  message::warning($extra_fields[$property_id]->name . " " . t("got converted to integer before saving"));
	    	}
	    	$arr_freetext[$property_id] = $value;
    	  }
    	  break;
    		
    	case 'dropdown':
    	  $value = $fieldData->selected;
	   	  if ( !empty($value) ) {
    	  	$arr_selection[$property_id] = $value;
    	  }
    	  break;
    		
    	case 'month':
    	  // construct an int value out of month+year selected
    	  $month = (int)$fieldData->inputs[$fieldName . '_month']->selected;
    	  $year = (int)$fieldData->inputs[$fieldName . '_year']->selected;
    	  // Note: could do extra sanitization to ignore pointless values, but can cause no troubles, so nm
	   	  if ( !empty($month) && !empty($year) ) {
    	    $arr_freetext[$property_id] = 12 * $year + $month;
    	  }
    	  break;
    		
    	case 'radio':
    	  $value = $fieldData->inputs[$fieldName]->selected;
	   	  if ( !empty($value) ) {
    	    $arr_selection[$property_id] = $value;
    	  }
    	  break;
    		
    	case 'checkbox':
    	  foreach ( $fieldData->inputs as $selectionName => $selectionData ) {
    	  	$checked = $selectionData->checked;
    		if ( $checked )	{
		      $tmp = explode( '_', $selectionName );
			  $selection_id = array_pop( $tmp );
    		  $arr_selection[$property_id][] = $selection_id;
    		}
    	  }
    	  break;
      }
    }
    return array( $arr_freetext, $arr_selection );
  }

  /**
   * Get input in raw format
   */
  static function grab_form_input_raw($form) {
    $extra_fields = custom_fields::get_extra_fields();

    // damn vars can't be isset/empty checked without assigning to local vars first, being protected
    $arr_input = array();
    foreach ( $form->custom_fields->inputs as $fieldName => $fieldData )
    {
    	$tmp = explode( '_', $fieldName );
    	$property_id = array_pop( $tmp );
    	switch ( $extra_fields[$property_id]->type )	{
    		case 'freetext':
    		case 'integer':
    			$value = $fieldData->value;
	   			if ( isset($value) ) {
	    			$arr_input[$fieldName] = $value;
    			}
    			break;
    		
    		case 'dropdown':
    			$value = $fieldData->selected;
	   			if ( !empty($value) ) {
	    			$arr_input[$fieldName] = $value;
    			}
    			break;
    		
    		case 'month':
    			// construct an int value out of month+year selected
    			$month = (int)$fieldData->inputs[$fieldName . '_month']->selected;
    			$year = (int)$fieldData->inputs[$fieldName . '_year']->selected;
    			// TODO: sanitization?

	   			if ( !empty($month) && !empty($year) ) {
	    			$arr_input[$fieldName . "_month"] = $month;
    				$arr_input[$fieldName . "_year"] = $year;
    			}
    			break;
    		
    		case 'radio':
    			$value = $fieldData->inputs[$fieldName]->selected;
	   			if ( !empty($value) ) {
	    			$arr_input[$fieldName] = $value;
    			}
    			break;
    		
    		case 'checkbox':
    			foreach ( $fieldData->inputs as $selectionName => $selectionData )
    			{
    				$checked = $selectionData->checked;
    				if ( $checked )	{
					   	$tmp = explode( '_', $selectionName );
				    	$selection_id = array_pop( $tmp );
        				$arr_input[$fieldName . "_" . $selection_id] = 1;
    				}
    			}
    			break;
    	}
    }
    return $arr_input;
  }

  /**
   * Convert active filters into param => value pairs, suitable to converting into hidden <input />s for sending again via POST
   */
  static function active_filter_2_param_pairs($arr_active_filter) {
    $arr_return_pairs = array();
    foreach ($arr_active_filter as $property_id => $arr_meta) {
      $onclick = array();
      $onclick[0]['param'] = "cf_" . $property_id;

      foreach ( $arr_meta['bits'] as $bit_index => $arr_bit ) {
        // work out onclick values
        switch ( $arr_meta['type'] ) {
          case "freetext":
          case "integer":
            $onclick[0]['value'] = $arr_bit['value'];
            break;
      
          case "dropdown":
          case "radio":
            $onclick[0]['value'] = $arr_bit['selection_id'];
            break;
      
          case "month":
            list($month, $year) = custom_fields::convert_month_int( $arr_bit['value'] );
            $onclick[1]['param'] = $onclick[0]['param'];

            $onclick[0]['param'] .= "_month";
            $onclick[0]['value'] = $month;

            $onclick[1]['param'] .= "_year";
            $onclick[1]['value'] = $year;
            break;
      
          case "checkbox":
            $onclick[0]['param'] .= '_' . $arr_bit['selection_id'];
            $onclick[0]['value'] = 1;
            break;
        }
      
        foreach ( $onclick as $index => $arr_param ) {
          $arr_return_pairs[$property_id][$bit_index][] = array( "param" => $arr_param["param"], "value" => $arr_param["value"] );
        }
      }
    }
    return $arr_return_pairs;
  }

  static function process_item_form_input($item, $form) {
    // delete current metadata info
    custom_fields::clear_all($item);

    // vars can't be isset/empty checked without assigning to local vars first, being protected
    list($arr_freetext, $arr_selection) = custom_fields::grab_form_input_values( $form );

	// write values into database
	// first freetext values into custom_fields_freetext_map table
    if ( !empty($arr_freetext) ) {
      custom_fields::add_freetext( $item, $arr_freetext );
    }
	// then single/multi selection values into custom_fields_selection_map table
    if ( !empty($arr_selection) ) {
      // any arr_selection[$property_id] could be multidimensional in here
      custom_fields::add_selection( $item, $arr_selection );
    }
    // TODO/BUG: trigger a manual save, otherwise its not saving all the time
    custom_fields::update($item);
  }


  /*** FIELDS MANAGEMENT FUNCTIONS ***/

  static function lookup_field($property_id) {
    $arr_fields = custom_fields::get_extra_fields(false, $property_id);
    return isset($arr_fields[$property_id]) ? $arr_fields[$property_id] : false;
  }

  static function update_field($id, $field_data, $no_selection_handling=false) {
    $db = Database::instance();

    // TODO: drop all current mappings if type is being switched to non compatible or selections (with mapped items) are being altered,
    // but the let the user know
    if ( !in_array($field_data["type"], array("freetext", "month", "integer")) && !$no_selection_handling ) {
      // insert new selections
      custom_fields::override_selections($id, $field_data["selections"]);
    }

    foreach ( array("name","type","context") as $str_input ) {
      $field_data[$str_input] = $db->escape($field_data[$str_input]);
    }

    $query =
      "UPDATE {custom_fields_properties} " .
      "SET name = '$field_data[name]', type = '$field_data[type]', context = '$field_data[context]', " .
      " searchable = " . intval($field_data["searchable"]) . ", thumb_view = " . intval($field_data["thumb_view"]) . ", create_input = " . intval($field_data["create_input"]) . "," .
      " max_length = " . (!empty($field_data["max_length"]) ? intval($field_data["max_length"]) : "NULL") . " " . 
      "WHERE id = $id";
    $db->query($query);

    // update language dependant value (for future use)
    $locale = custom_fields::get_active_local();
    $query =
      "UPDATE {custom_fields_properties_multilang} " .
      "SET name = '$field_data[name]' " .
      "WHERE property_id = $id AND locale = '" . $db->escape($locale) . "'";
    $res = $db->query($query);
  }

  // TODO: merge this with update_field() and let $id=false in params
  static function add_field($field_data) {
    $db = Database::instance();

    foreach ( array("name","type","context") as $str_input ) {
      $field_data[$str_input] = $db->escape($field_data[$str_input]);
    }

    // Note: using alternated syntax (1 of the 2 supported by MySql) so can be kept almost identical to save_field()
    $query =
      "INSERT INTO {custom_fields_properties} " .
      "SET name = '$field_data[name]', type = '$field_data[type]', context = '$field_data[context]', " .
      " searchable = " . intval($field_data["searchable"]) . ", thumb_view = " . intval($field_data["thumb_view"]) . ", create_input = " . intval($field_data["create_input"]) . "," .
      " max_length = " . (!empty($field_data["max_length"]) ? intval($field_data["max_length"]) : "NULL");
    // mysql resource object
    $res = $db->query($query);

    // add language dependant value (for future use)
    $locale = custom_fields::get_active_local();
    $query =
      "INSERT INTO {custom_fields_properties_multilang} (property_id, locale, name) " .
      "VALUES (" . $res->insert_id() . ", '" . $db->escape($locale) . "', '$field_data[name]')";
    $res2 = $db->query($query);

    // save its selections
    if ( !in_array($field_data["type"], array("freetext", "month", "integer")) ) {
      // insert new selections
      custom_fields::add_selections($res->insert_id(), $field_data["selections"]);
    }
  }


  /* add _new_ selections to a field */
  static function add_selections($id, $arr_selections) {
    $db = Database::instance();

    // insert new selections
    $insert = array();
    foreach( $arr_selections as $name ) {
	    $insert[] = "(" . $id . ",'" . $db->escape($name) . "')";
    }
    $query =
      "INSERT INTO {custom_fields_selection} (property_id, value) " .
      "VALUES " . implode(',', $insert);
    $db->query($query);

    // add multilang value
    $locale = custom_fields::get_active_local();
    $query =
      "INSERT IGNORE INTO {custom_fields_selection_multilang} (property_id, selection_id, locale, value) " .
      "SELECT $id, selection_id, '" . $db->escape($locale)   . "', value " .
      "FROM {custom_fields_selection} " .
      "WHERE property_id = $id";
    $db->query($query);
  }

  /* add/update selections of a field */
  // @TODO: merge this with add_selections()
  static function override_selections($id, $arr_selections) {
    $db = Database::instance();

    // verifiy which selections are getting changed (edited/deleted)
    $field = custom_fields::lookup_field($id);
    $arr_changed = array();
    foreach ($field->options as $sel_id => $val) {
      if (!isset($arr_selections[$sel_id]) || $arr_selections[$sel_id] != $val) {
        $arr_changed[] = $sel_id;
      }
    }
    // mark search records linking to these selections as dirty
    $filter_changing = "AND selection_id IN (" . implode(',',$arr_changed) . ")";
    if (!empty($arr_changed)) {
      $query = "UPDATE {custom_fields_records} r JOIN {custom_fields_selection_map} sm ON sm.item_id = r.item_id " .
        "SET r.dirty = 1 " .
        "WHERE sm.property_id = $id $filter_changing";
      $db->query($query);

      // remove current ones
      $db->query("DELETE FROM {custom_fields_selection} WHERE property_id = $id $filter_changing");

      // delete maps to selections just deleted
      $db->query("DELETE FROM {custom_fields_selection_map} WHERE property_id = $id $filter_changing");

      // delete multilang values of changed fields
      $db->query("DELETE FROM {custom_fields_selection_multilang} WHERE property_id = $id $filter_changing");

      // raise index out of date warning
      custom_fields::check_index();
    }

    // insert new selections
    $insert = array();
    foreach($arr_selections as $index => $selection) {
      $insert[] = "(" . $id . "," . ($index) . ",'" . $db->escape($selection) . "')";
    }
    $query =
      "INSERT INTO {custom_fields_selection} (property_id, selection_id, value) " .
      "VALUES " . implode(',', $insert) . " ON DUPLICATE KEY UPDATE value = VALUES(value)";
    $db->query($query);

    // add multilang values for these
    $locale = custom_fields::get_active_local();
    $query =
      "INSERT INTO {custom_fields_selection_multilang} (property_id, selection_id, locale, value) " .
      "(SELECT $id, selection_id, '" . $db->escape($locale)   . "', value " .
      "FROM {custom_fields_selection} " .
      "WHERE property_id = $id) " .
      "ON DUPLICATE KEY UPDATE value=VALUES(value)";
    $db->query($query);
  }

  /**
   * Convert active filters into hidden input fields
   */
  /* Not used
  static function input_2_hidden($arr_input) {
      $input = "";
      foreach ( $arr_input as $fieldName => $value )
      {
        $input .= "<input type=\"hidden\" name=\"" . $fieldName . "\" value=\"" . html::clean_attribute($value) . "\"/>";
      }
  }
  */

  /**
   * Get active local
   */
  static function get_active_local() {
    $cookie_locale = locales::cookie_locale();
    $installed_locales = locales::installed();
    $locale = isset($cookie_locale) && isset($installed_locales[$cookie_locale]) 
        ? $cookie_locale : module::get_var("gallery", "default_locale");

    return $locale;
  }
}
