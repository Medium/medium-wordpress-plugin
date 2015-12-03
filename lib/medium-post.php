<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

/**
 * Representation of a Medium post.
 */
class Medium_Post {
  public $author_image_url;
  public $author_url;
  public $byline_name;
  public $byline_email;
  public $cross_link;
  public $id;
  public $follower_notification;
  public $license;
  public $publication_id;
  public $status;
  public $url;

  /**
   * Gets the Medium post associated with the supplied post Id.
   */
  public static function get_by_wp_id($post_id) {
    if ($post_id) {
      $medium_post = get_post_meta($post_id, "medium_post", true);
    }
    if (!$medium_post) $medium_post = new Medium_Post();
    return $medium_post;
  }

  /**
   * Save the Medium post associated with the supplied post Id.
   */
  public function save($post_id) {
    update_post_meta($post_id, "medium_post", $this);
  }
}
