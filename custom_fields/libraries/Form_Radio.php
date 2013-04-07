<?php defined('SYSPATH') or die('No direct script access.');
/**
* FORGE dropdown input library to replace the really lame one in there, which is simply improper for singleselect radio implementation.
*/

class Form_Radio_Core extends Form_Input {

  protected $data = array
  (
    'name' => '',
    'class' => 'radio',
    'type'=>'radio',
    'options'=>array()
  );

  protected $protect = array('type');

  public function __get($key)
  {
    if ( $key == 'value')
    {
      return $this->selected;
    }
    return parent::__get( $key );
  }

  public function html_element()
  {
    // Import base data
    $data = $this->data;
    // Get the options and default selection
    $options = arr::remove('options', $data);
    $selected = arr::remove('selected', $data);
  
    // martin hack
    unset( $data['label'] );
    $html = '';
    foreach( $options as $option => $labelText ) {
      $html .=
      '<label>' 
      . form::radio( array('name' => $data['name'], 'id' => $data['name'] . "_" . $option), $option, ($this->value ? $this->value == $option : $data['default'] == $option) ) 
      . ' ' . $labelText . '</label>';
    }
    return $html;
  }
  
  protected function load_value()
  {
    if ( is_bool($this->valid) )
    {
      return;
    }
    $this->data['selected'] = $this->input_value( $this->name );
  }

} // End Form radio
