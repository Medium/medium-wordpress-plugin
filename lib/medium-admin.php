<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

require_once(MEDIUM_PLUGIN_DIR . "lib/medium-post.php");
require_once(MEDIUM_PLUGIN_DIR . "lib/medium-user.php");
require_once(MEDIUM_PLUGIN_DIR . "lib/medium-view.php");

class Medium_Admin {

  private static $_initialised = false;

  /**
   * Initialises actions and filters.
   */
  public static function init() {
    if (self::$_initialised) return;
    self::$_initialised = true;

    session_start();

    add_action("admin_init", array("Medium_Admin", "admin_init"));
    add_action("admin_notices", array("Medium_Admin", "admin_notices"));

    add_action("show_user_profile", array("Medium_Admin", "show_user_profile"));
    add_action("edit_user_profile", array("Medium_Admin", "show_user_profile"));

    add_action("personal_options_update", array("Medium_Admin", "personal_options_update"));
    add_action("edit_user_profile_update", array("Medium_Admin", "personal_options_update"));

    add_action("add_meta_boxes_post", array("Medium_Admin", "add_meta_boxes_post"));

    add_action("save_post", array("Medium_Admin", "save_post"), 10, 2);
  }

  // Actions and hooks.

  /**
   * Initialises admin functionality.
   */
  public static function admin_init() {
    load_plugin_textdomain(MEDIUM_TEXTDOMAIN);

    wp_enqueue_script("medium_admin_js", MEDIUM_PLUGIN_URL . "js/admin.js", array(), MEDIUM_VERSION);
    wp_enqueue_style("medium_admin_css", MEDIUM_PLUGIN_URL . "css/admin.css", array(), MEDIUM_VERSION);
  }

  /**
   * Renders admin notices.
   */
  public static function admin_notices() {
    if (!isset($_SESSION["medium_notices"]) || !$_SESSION["medium_notices"]) return;
    foreach ($_SESSION["medium_notices"] as $name => $args) {
      Medium_View::render("notice-$name", $args);
    }
    $_SESSION["medium_notices"] = array();
  }

  /**
   * Handles the saving of personal options.
   */
  public static function personal_options_update($user_id) {
    if (!current_user_can("edit_user", $user_id)) return false;
    $token = $_POST["medium_integration_token"];
    $status = $_POST["medium_default_post_status"];
    $license = $_POST["medium_default_post_license"];
    $cross_link = $_POST["medium_default_post_cross_link"];

    $medium_user = Medium_User::get_by_wp_id($user_id);

    if ($medium_user->default_status != $status) {
      $medium_user->default_status = $status;
    }

    if ($medium_user->default_license != $license) {
      $medium_user->default_license = $license;
    }

    if ($medium_user->default_cross_link != $cross_link) {
      $medium_user->default_cross_link = $cross_link;
    }

    if (!$token) {
      $medium_user->id = "";
      $medium_user->image_url = "";
      $medium_user->name = "";
      $medium_user->token = "";
      $medium_user->url = "";
    } else if ($token != $medium_user->token) {
      try {
        // Check that the token is valid.
        $user = self::get_medium_user_info($token);
        $medium_user->id = $user->id;
        $medium_user->image_url = $user->imageUrl;
        $medium_user->name = $user->name;
        $medium_user->token = $token;
        $medium_user->url = $user->url;

        self::_add_notice("connected", array(
          "user" => $user
        ));
      } catch (Exception $e) {
        self::_add_api_error_notice($e, $token);
      }
    }
    $medium_user->save($user_id);

    return true;
  }

  /**
   * Adds Medium integration settings to the user profile.
   */
  public static function show_user_profile($user) {
    $medium_user = Medium_User::get_by_wp_id($user->ID);
    Medium_View::render("form-user-profile", array(
      "medium_post_statuses" => self::_get_post_statuses(),
      "medium_post_licenses" => self::_get_post_licenses(),
      "medium_post_cross_link_options" => self::_get_post_cross_link_options(),
      "medium_user" => $medium_user
    ));
  }

  /**
   * Renders the cross-posting options in the edit post sidebar.
   */
  public static function add_meta_boxes_post($post) {
    add_meta_box("medium", "Medium", array("Medium_Admin", "meta_box_callback"),
        null, "side", "high");
  }

  /**
   * Save Medium metadata when a post is saved.
   * Potentially crossposts to Medium if the conditions are right.
   */
  public static function save_post($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $medium_post = Medium_Post::get_by_wp_id($post_id);

    // If this post has already been sent to Medium, no need to do anything.
    if ($medium_post->id) return;

    if (isset($_REQUEST["medium-status"])) {
      $medium_post->status = $_REQUEST["medium-status"];
    }
    if (isset($_REQUEST["medium-license"])) {
      $medium_post->license = $_REQUEST["medium-license"];
    }
    if (isset($_REQUEST["medium-cross-link"])) {
      $medium_post->cross_link = $_REQUEST["medium-cross-link"];
    }

    // If the post isn't published, no need to do anything else.
    $published = $post->post_status == "publish";

    // If we don't want to crosspost this post to Medium, no need to do anything else.
    $skipCrossposting = $medium_post->status == "none";

    // If the user isn't connected, no need to do anything.
    $medium_user = Medium_User::get_by_wp_id($post->post_author);
    $connected = $medium_user->id && $medium_user->token;

    if (!$published || $skipCrossposting || !$connected) {
      // Save the updated license and status.
      $medium_post->save($post_id);
      return;
    }

    // At this point, we are not auto-saving, the post is published, we are
    // connected, we haven't sent it to Medium previously, and we want to send it.

    try {
      $created_medium_post = self::crosspost($post, $medium_post, $medium_user);
    } catch (Exception $e) {
      self::_add_api_error_notice($e, $medium_user->token);
      return;
    }

    $medium_post->id = $created_medium_post->id;
    $medium_post->url = $created_medium_post->url;
    $medium_post->author_image_url = $medium_user->image_url;
    $medium_post->author_url = $medium_user->url;
    $medium_post->save($post_id);

    self::_add_notice("published", array(
      "medium_post" => $medium_post,
      "medium_post_statuses" => self::_get_post_statuses()
    ));
    return;
  }

  // Utilities

  /**
   * Callback that renders the Crosspost meta box.
   */
  public static function meta_box_callback($post, $args) {
    global $current_user;

    $medium_logo_url = MEDIUM_PLUGIN_URL . 'i/logo.png';
    $medium_post = Medium_Post::get_by_wp_id($post->ID);
    $medium_user = Medium_User::get_by_wp_id($post->post_author);
    if ($medium_post->id) {
      // Already connected.
      if ($medium_user->id) {
        $medium_post->author_url = $medium_user->url;
        $medium_post->author_image_url = $medium_user->image_url;
      }
      Medium_View::render("form-post-box-linked", array(
        "medium_post" => $medium_post,
        "medium_logo_url" => $medium_logo_url
      ));
    } else if ($medium_user->token && $medium_user->id) {
      // Can be connected.
      if (!$medium_post->license) {
        $medium_post->license = $medium_user->default_license;
      }
      if (!$medium_post->status) {
        $medium_post->status = $medium_user->default_status;
      }
      if (!$medium_post->cross_link) {
        // Default to no cross-linking, per WordPress guidelines.
        $medium_post->cross_link = $medium_user->default_cross_link;
      }
      $options_visibility_class = $medium_post->status == "none" ? "hidden" : "";
      Medium_View::render("form-post-box-actions", array(
        "medium_post" => $medium_post,
        "medium_user" => $medium_user,
        "medium_logo_url" => $medium_logo_url,
        "medium_post_statuses" => self::_get_post_statuses(),
        "medium_post_licenses" => self::_get_post_licenses(),
        "medium_post_cross_link_options" => self::_get_post_cross_link_options(),
        "options_visibility_class" => $options_visibility_class
      ));
    } else {
      // Needs token.
      Medium_View::render("form-post-box-actions-disabled", array(
        "edit_profile_url" => get_edit_user_link($current_user->ID) . '#medium'
      ));
    }
  }

  // API calls.

  /**
   * Creates a post on Medium.
   */
  public static function crosspost($post, $medium_post, $medium_user) {
    $tag_data = wp_get_post_tags($post->ID);
    $tags = array();
    foreach ($tag_data as $tag) {
      if ($tag->taxonomy == "post_tag") {
        $tags[] = $tag->name;
      }
    }

    $content = Medium_View::render("content-rendered-post", array(
      "title" => $post->post_title,
      "content" => self::_prepare_content($post),
      "cross_link" => $medium_post->cross_link == "yes",
      "site_name" => get_bloginfo('name'),
      "permalink" => get_permalink($post->ID)
    ), true);

    $body = array(
      "title" => $post->post_title,
      "content" => $content,
      "tags" => $tags,
      "contentFormat" => "html",
      "canonicalUrl" => $permalink,
      "license" => $medium_post->license,
      "publishStatus" => $medium_post->status
    );
    $data = json_encode($body);

    $headers = array(
      "Authorization: Bearer " . $medium_user->token,
      "Content-Type: application/json",
      "Accept: application/json",
      "Accept-Charset: utf-8",
      "Content-Length: " . strlen($data)
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.medium.com/v1/users/" . $medium_user->id . "/posts",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $data
    ));
    $result = curl_exec($curl);
    curl_close($curl);

    $payload = json_decode($result);
    if ($payload->errors) {
      $error = $payload->errors[0];
      throw new Exception($error->message, $error->code);
    }

    return $payload->data;
  }

  /**
   * Gets the Medium user's profile information.
   */
  public static function get_medium_user_info($integration_token) {
    $headers = array(
      "Authorization: Bearer " . $integration_token,
      "Accept: application/json",
      "Accept-Charset: utf-8"
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.medium.com/v1/me",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers
    ));
    $result = curl_exec($curl);
    curl_close($curl);

    $payload = json_decode($result);
    if ($payload->errors) {
      $error = $payload->errors[0];
      throw new Exception($error->message, $error->code);
    }

    return $payload->data;
  }

  // Data.

  /**
   * Returns an array of the valid post statuses.
   */
  private static function _get_post_statuses() {
    return array(
      "none" => __("None", MEDIUM_TEXTDOMAIN),
      "public" => __("Public", MEDIUM_TEXTDOMAIN),
      "draft" => __("Draft", MEDIUM_TEXTDOMAIN),
      "unlisted" => __("Unlisted", MEDIUM_TEXTDOMAIN)
    );
  }

  /**
   * Returns an array of the the valid post licenses.
   */
  private static function _get_post_licenses() {
    return array(
      "all-rights-reserved" => __("All rights reserved", MEDIUM_TEXTDOMAIN),
      "cc-40-by" => __("CC 4.0 BY", MEDIUM_TEXTDOMAIN),
      "cc-40-by-nd" => __("CC 4.0 BY-ND", MEDIUM_TEXTDOMAIN),
      "cc-40-by-sa" => __("CC 4.0 BY-SA", MEDIUM_TEXTDOMAIN),
      "cc-40-by-nc" => __("CC 4.0 BY-NC", MEDIUM_TEXTDOMAIN),
      "cc-40-by-nc-nd" => __("CC 4.0 BY-NC-ND", MEDIUM_TEXTDOMAIN),
      "cc-40-by-nc-sa" => __("CC 4.0 BY-NC-SA", MEDIUM_TEXTDOMAIN),
      "cc-40-zero" => __("CC Copyright waiver", MEDIUM_TEXTDOMAIN),
      "public-domain" => __("Public domain", MEDIUM_TEXTDOMAIN)
    );
  }

  /**
   * Returns an array of the valid post statuses.
   */
  private static function _get_post_cross_link_options() {
    return array(
      "no" => __("No", MEDIUM_TEXTDOMAIN),
      "yes" => __("Yes", MEDIUM_TEXTDOMAIN)
    );
  }

  // Feedback.

  /**
   * Adds an API error notice.
   */
  public static function _add_api_error_notice(Exception $e, $token) {
    $args = array(
      "token" => $token
    );
    switch ($e->getCode()) {
      case 6000:
      case 6001:
      case 6003:
        $type = "invalid-token";
        break;
      case 6027:
        $type = "api-disabled";
        break;
      default:
        $args["message"] = $e->getMessage();
        $args["code"] = $e->getCode();
        $type = "something-wrong";
        break;
    }
    self::_add_notice($type, $args);
  }

  // Formatting

  /**
   * Given a post, returns content suitable for sending to Medium.
   */
  private static function _prepare_content($post) {
    // Add paragraph tags.
    $post_content = wpautop($post->post_content);

    // Best effort. Regex parsing of HTML is a bad idea, generally, but including
    // a full parser just for this case is over the top. This will match things
    // inside, for example, <pre> and <xmp> tags. ¯\_(ツ)_/¯
    preg_match_all('/<(?:a|img)[^>]+(?:href|src)="(?:\/[^"]*)"/', $post_content, $matches);
    if (!$matches[0]) return $post_content;

    // Replace relative URLs in links and image sources.
    $site_url = site_url();
    $replacements = array();
    foreach ($matches[0] as $match) {
      $replacement = preg_replace('/href="(\/[^"]*)"/', 'href="' . $site_url . '$1"', $match);
      $replacement = preg_replace('/src="(\/[^"]*)"/', 'src="' . $site_url . '$1"', $replacement);
      $replacements[] = $replacement;
    }
    return str_replace($matches[0], $replacements, $post_content);
  }

  /**
   * Adds a notice to the admin panel.
   */
  private static function _add_notice($name, array $args = array()) {
    if (!isset($_SESSION["medium_notices"])) {
      $_SESSION["medium_notices"] = array();
    }
    $_SESSION["medium_notices"][$name] = $args;
  }
}
