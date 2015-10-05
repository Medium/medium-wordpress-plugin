<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

class Medium_View {

  /**
   * Renders a template.
   */
  public static function render($name, array $args = array(), $return = false) {
    $data = new stdClass();
    foreach ($args as $key => $val) {
      $data->$key = $val;
    }
    ob_start();
    include(MEDIUM_PLUGIN_DIR . 'views/'. $name . '.phtml');
    if ($return) return ob_get_clean();
    ob_end_flush();
  }
}
