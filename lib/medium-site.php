<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

require_once(MEDIUM_PLUGIN_DIR . "lib/medium-post.php");
require_once(MEDIUM_PLUGIN_DIR . "lib/medium-view.php");

class Medium_Site {

  private static $_initialised = false;

  /**
   * Initialises actions and filters.
   */
  public static function init() {
    if (self::$_initialised) return;
    self::$_initialised = true;

    add_filter("the_content", array("Medium_Site", "the_content"), 100);
  }

  // Actions and hooks.

  public static function the_content($content) {
    global $post;

    $medium_post = Medium_Post::get_by_wp_id($post->ID);
    if ($medium_post->id && $medium_post->cross_link) {
      $content = Medium_View::render("content-cross-linked", array(
        "content" => $content,
        "medium_post" => $medium_post
      ));
    }

    return $content;
  }
}
