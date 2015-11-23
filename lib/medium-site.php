<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

include_once(MEDIUM_PLUGIN_DIR . "lib/medium-post.php");
include_once(MEDIUM_PLUGIN_DIR . "lib/medium-view.php");

class Medium_Site {

  private static $_initialised = false;

  /**
   * Initialises actions and filters.
   */
  public static function init() {
    if (self::$_initialised) return;
    self::$_initialised = true;

    add_filter("the_content", array("Medium_Site", "the_content"), 10);

    add_shortcode("medium_cross_link", array("Medium_Site", "shortcode_cross_link"));
  }

  // Actions and hooks.

  public static function the_content($content) {
    global $post;
    if (!is_single() || !is_main_query()) return $content;

    $medium_post = Medium_Post::get_by_wp_id($post->ID);
    if ($medium_post->id && $medium_post->cross_link == "yes") {
      // We cannot directly render here because the_content filters are also
      // executed for the_excerpt (!). Best solution is a shortcode that we
      // then render, as shortcodes are ignored by the_excerpt.
      // @see https://github.com/Medium/medium-wordpress-plugin/issues/32
      // @see https://github.com/Medium/medium-wordpress-plugin/issues/29
      $content .= '[medium_cross_link url="' . $medium_post->url . '"]';
    }

    return $content;
  }

  public static function shortcode_cross_link($attributes) {
    return Medium_View::render("content-cross-linked", array(
      "url" => $attributes["url"]
    ), true);
  }


}
