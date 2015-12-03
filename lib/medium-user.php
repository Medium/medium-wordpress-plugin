<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

/**
 * Representation of a Medium user.
 */
class Medium_User {
  public $default_cross_link = "no";
  public $default_follower_notification = "yes";
  public $default_license = "all-rights-reserved";
  public $default_publication_id = "";
  public $default_status = "public";
  public $id;
  public $image_url;
  public $name;
  public $publications = array();
  public $token;
  public $url;
  public $username;

  /**
   * Gets the Medium user associated with the supplied user Id.
   */
  public static function get_by_wp_id($user_id) {
    if ($user_id) {
      $medium_user = get_the_author_meta("medium_user", $user_id);
    }
    if (!$medium_user) $medium_user = new Medium_User();
    return $medium_user;
  }

  /**
   * Saves the Medium user associated with the supplied user Id.
   */
  public function save($user_id) {
    update_user_meta($user_id, "medium_user", $this);
  }
}
