<?php defined("SYSPATH") or die("No direct script access.") ?>
<?php
  print $form;

  // Print out the list of custom data as clickable links.
  $arr_pairs = custom_fields::active_filter_2_param_pairs( $all_custom );

  foreach ($all_custom as $property_id => $arr_meta) {
    print "<div>" . html::clean($arr_meta['name']) . ": ";
    $bitCount = count($arr_meta['bits']);
    
    $i = 1;
    foreach ( $arr_meta['bits'] as $bit_index => $arr_bit )
    {
      // for month type format data for display
      if ( $arr_meta['type'] == 'month' )
      {
        list($month, $year) = custom_fields::convert_month_int( $arr_bit['value'] );
      }
      if ( $arr_meta['searchable'] ) {
        print "<a href=\"#\" onclick=\"";

        foreach ( $arr_pairs[$property_id][$bit_index] as $index => $arr_param )
        {
          $last = $index + 1 == count( $arr_pairs[$property_id][$bit_index] );
          print ($last ? "return " : "") . "custom_fields_submit('" . $arr_param['param'] . "','" . $arr_param['value'] . "'" . (!$last ? ",false" : "") . ");";
        }
        print "\" >";
      }
      print ($arr_meta['type'] == 'month' ? $month . '/' . $year : html::clean($arr_bit['value']));
      if ( $arr_meta['searchable'] ) {
        print "</a>";
      }
      if ( $i != $bitCount )
      {
        print ", ";
      }
      $i++;
    }
    print "</div>";
  }
?>
