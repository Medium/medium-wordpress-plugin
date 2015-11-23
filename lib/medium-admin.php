<?php
// Copyright 2015 Medium
// Licensed under the Apache License, Version 2.0.

include_once(MEDIUM_PLUGIN_DIR . "lib/medium-post.php");
include_once(MEDIUM_PLUGIN_DIR . "lib/medium-publication.php");
include_once(MEDIUM_PLUGIN_DIR . "lib/medium-user.php");
include_once(MEDIUM_PLUGIN_DIR . "lib/medium-view.php");

define("NO_PUBLICATION", -1);

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
    add_action("wp_ajax_medium_refresh_publications", array("Medium_Admin", "refresh_publications"));

    add_action("add_meta_boxes_post", array("Medium_Admin", "add_meta_boxes_post"));

    add_action("save_post", array("Medium_Admin", "save_post"), 10, 2);
  }

  // Actions and hooks.

  /**
   * Initialises admin functionality.
   */
  public static function admin_init() {
    load_plugin_textdomain("medium");

    wp_register_script("medium_admin_js", MEDIUM_PLUGIN_URL . "js/admin.js");
    wp_localize_script("medium_admin_js", "medium", array(
    	"errorMissingScope" => __("An updated integration token is needed to perform this action. Please create a new integration token from your Medium settings page and set it on your WordPress profile above.", "medium"),
      "errorUnknown" => __("An unknown error occurred (%s).", "medium")
    ));
    wp_enqueue_script("medium_admin_js");

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
    $follower_notification = $_POST["medium_default_follower_notification"];
    $publication_id = $_POST["medium_default_publication_id"];

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

    if ($medium_user->default_follower_notification != $follower_notification) {
      $medium_user->default_follower_notification = $follower_notification;
    }

    if ($medium_user->default_publication_id != $publication_id) {
      $medium_user->default_publication_id = $publication_id;
    }

    if (!$token) {
      $medium_user->id = "";
      $medium_user->image_url = "";
      $medium_user->name = "";
      $medium_user->token = "";
      $medium_user->url = "";
      $medium_user->default_publication_id = "";
      $medium_user->publications = array();
    } else if ($token != $medium_user->token) {
      try {
        // Check that the token is valid.
        $user = self::get_medium_user_info($token);

        // Refresh the set of publications the user can contribute to.
        $medium_user->publications = self::get_contributing_publications($token, $user->id);

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
   * Handles the AJAX callback to refresh the publication list for a user.
   */
  public static function refresh_publications() {
    global $current_user;
    $medium_user = Medium_User::get_by_wp_id($current_user->ID);
    try {
      // Persist the publications for next time.
      $medium_user->publications = self::get_contributing_publications($medium_user->token, $medium_user->id);
      $medium_user->save($current_user->ID);

      echo json_encode(self::_get_user_publication_options($medium_user));
    } catch (Exception $e) {
      echo self::_encode_ajax_error($e);
    }

    die();
  }

  /**
   * Adds Medium integration settings to the user profile.
   */
  public static function show_user_profile($user) {
    $medium_user = Medium_User::get_by_wp_id($user->ID);
    Medium_View::render("form-user-profile", array(
      "medium_post_statuses" => self::_get_post_statuses(),
      "medium_post_licenses" => self::_get_post_licenses(),
      "medium_boolean_options" => self::_get_boolean_options(),
      "medium_publication_options" => self::_get_user_publication_options($medium_user),
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
   * Potentially cross-posts to Medium if the conditions are right.
   */
  public static function save_post($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $allowed_post_types = apply_filters("medium_allowed_post_types", array("post"));
    if (!in_array($post->post_type, $allowed_post_types)) return;

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
    if (isset($_REQUEST["medium-follower-notification"])) {
      $medium_post->follower_notification = $_REQUEST["medium-follower-notification"];
    }
    if (isset($_REQUEST["medium-publication-id"])) {
      $medium_post->publication_id = $_REQUEST["medium-publication-id"];
    }

    // If the post isn't published, no need to do anything else.
    $published = $post->post_status == "publish";

    // If we don't want to cross-post this post to Medium, no need to do anything else.
    $skip_cross_posting = $medium_post->status == "none";

    // If the user isn't connected, no need to do anything.
    $medium_user = Medium_User::get_by_wp_id($post->post_author);
    $connected = $medium_user->id && $medium_user->token;

    if (!$published || $skip_cross_posting || !$connected) {
      // Save the updated license and status.
      $medium_post->save($post_id);
      return;
    }

    // At this point, we are not auto-saving, the post is published, we are
    // connected, we haven't sent it to Medium previously, and we want to send it.

    try {
      $created_medium_post = self::cross_post($post, $medium_post, $medium_user);
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
   * Callback that renders the Cross-post meta box.
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
      if (!$medium_post->cross_link) {
        // Default to no cross-linking, per WordPress guidelines.
        $medium_post->cross_link = $medium_user->default_cross_link;
      }
      if (!$medium_post->follower_notification) {
        // Default to notifying Medium followers.
        $medium_post->follower_notification = $medium_user->default_follower_notification;
      }
      if (!$medium_post->publication_id) {
        // Default to none.
        $medium_post->publication_id = $medium_user->default_publication_id ?: NO_PUBLICATION;
      }

      $publication_options = self::_get_user_publication_options($medium_user);
      $publishable = $publication_options[$medium_post->publication_id]->publishable;

      if (!$medium_post->status) {
        $medium_post->status = $medium_user->default_status;
      }
      if (($medium_post->status == "unlisted" || $medium_post->status == "public") && !$publishable) {
        $medium_post->status = "draft";
      }

      $options_visibility_class = $medium_post->status == "none" ? "hidden" : "";
      Medium_View::render("form-post-box-actions", array(
        "medium_post" => $medium_post,
        "medium_user" => $medium_user,
        "medium_logo_url" => $medium_logo_url,
        "medium_post_statuses" => self::_get_post_statuses(),
        "medium_post_licenses" => self::_get_post_licenses(),
        "medium_boolean_options" => self::_get_boolean_options(),
        "medium_publication_options" => $publication_options,
        "options_visibility_class" => $options_visibility_class,
        "default_publication_publishable" => $publishable
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
  public static function cross_post($post, $medium_post, $medium_user) {
    $tag_data = wp_get_post_tags($post->ID);
    $tags = array();
    foreach ($tag_data as $tag) {
      if ($tag->taxonomy == "post_tag") {
        $tags[] = $tag->name;
      }
    }

    $permalink = get_permalink($post->ID);
    $content = Medium_View::render("content-rendered-post", array(
      "title" => $post->post_title,
      "content" => self::_prepare_content($post),
      "cross_link" => $medium_post->cross_link == "yes",
      "site_name" => get_bloginfo('name'),
      "permalink" => $permalink
    ), true);

    $body = array(
      "title" => $post->post_title,
      "content" => $content,
      "tags" => $tags,
      "contentFormat" => "html",
      "canonicalUrl" => $permalink,
      "license" => $medium_post->license,
      "publishStatus" => $medium_post->status,
      "publishedAt" => mysql2date('c', $post->post_date),
      "notifyFollowers" => $medium_post->follower_notification == "yes"
    );
    $data = json_encode($body);

    $headers = array(
      "Authorization" => "Bearer " . $medium_user->token,
      "Content-Type" => "application/json",
      "Accept" => "application/json",
      "Accept-Charset" => "utf-8"
    );

    if ($medium_post->publication_id != NO_PUBLICATION) {
      $path = "/publications/{$medium_post->publication_id}/posts";
    } else {
      $path = "/users/{$medium_user->id}/posts";
    }

    $response = wp_remote_post("https://api.medium.com/v1$path", array(
      "headers" => $headers,
      "body" => $data,
      "user-agent" => "MonkeyMagic/1.0"
    ));

    return self::_handle_response($response);
  }

  /**
   * Gets the publications that a user can contribute to.
   */
  public static function get_contributing_publications($integration_token, $medium_user_id) {
    $publications = self::get_publications($integration_token, $medium_user_id);
    $contributing_publications = array();
    foreach ($publications as $publication) {
      $contributors = self::get_publication_contributors($integration_token, $publication->id);
      foreach ($contributors as $contributor) {
        if ($contributor->userId == $medium_user_id) {
          $contributing_publication = new Medium_Publication();
          $contributing_publication->id = $publication->id;
          $contributing_publication->image_url = $publication->imageUrl;
          $contributing_publication->name = $publication->name;
          $contributing_publication->description = $publication->description;
          $contributing_publication->url = $publication->url;
          $contributing_publication->role = $contributor->role;
          $contributing_publications[$publication->id] = $contributing_publication;
        }
      }
    }
    return $contributing_publications;
  }

  /**
   * Gets the user's publications on Medium.
   */
  public static function get_publications($integration_token, $medium_user_id) {
    $headers = array(
      "Authorization" => "Bearer " . $integration_token,
      "Accept" => "application/json",
      "Accept-Charset" => "utf-8"
    );

    $response = wp_remote_get("https://api.medium.com/v1/users/$medium_user_id/publications", array(
      "headers" => $headers,
      "user-agent" => "MonkeyMagic/1.0"
    ));

    return self::_handle_response($response);
  }

  public static function get_publication_contributors($integration_token, $publication_id) {
    $headers = array(
      "Authorization" => "Bearer " . $integration_token,
      "Accept" => "application/json",
      "Accept-Charset" => "utf-8"
    );

    $response = wp_remote_get("https://api.medium.com/v1/publications/$publication_id/contributors", array(
      "headers" => $headers,
      "user-agent" => "MonkeyMagic/1.0"
    ));

    return self::_handle_response($response);
  }

  /**
   * Gets the Medium user's profile information.
   */
  public static function get_medium_user_info($integration_token) {
    $headers = array(
      "Authorization" => "Bearer " . $integration_token,
      "Accept" => "application/json",
      "Accept-Charset" => "utf-8"
    );

    $response = wp_remote_get("https://api.medium.com/v1/me", array(
      "headers" => $headers,
      "user-agent" => "MonkeyMagic/1.0"
    ));

    return self::_handle_response($response);
  }

  // Data.

  /**
   * Returns an array of the valid post statuses.
   */
  private static function _get_post_statuses() {
    return array(
      "none" => __("None", "medium"),
      "public" => __("Public", "medium"),
      "draft" => __("Draft", "medium"),
      "unlisted" => __("Unlisted", "medium")
    );
  }

  /**
   * Returns an array of the the valid post licenses.
   */
  private static function _get_post_licenses() {
    return array(
      "all-rights-reserved" => __("All rights reserved", "medium"),
      "cc-40-by" => __("CC 4.0 BY", "medium"),
      "cc-40-by-nd" => __("CC 4.0 BY-ND", "medium"),
      "cc-40-by-sa" => __("CC 4.0 BY-SA", "medium"),
      "cc-40-by-nc" => __("CC 4.0 BY-NC", "medium"),
      "cc-40-by-nc-nd" => __("CC 4.0 BY-NC-ND", "medium"),
      "cc-40-by-nc-sa" => __("CC 4.0 BY-NC-SA", "medium"),
      "cc-40-zero" => __("CC Copyright waiver", "medium"),
      "public-domain" => __("Public domain", "medium")
    );
  }

  /**
   * Returns an array of boolean options for cross-linking and follower notification.
   */
  private static function _get_boolean_options() {
    return array(
      "no" => __("No", "medium"),
      "yes" => __("Yes", "medium")
    );
  }

  /**
   * Returns an array of the publications the supplied user can publish into.
   */
  private static function _get_user_publication_options(Medium_User $medium_user) {
    $default_option = new stdClass();
    $default_option->name = __("None", "medium");
    $default_option->publishable = true;
    $options[NO_PUBLICATION] = $default_option;

    foreach ($medium_user->publications as $publication) {
      if ($publication->role == "writer") {
        $publication_name = sprintf(__("%s (Draft only)", "medium"), $publication->name);
      } else {
        $publication_name = $publication->name;
      }
      $option = new stdClass();
      $option->name = $publication_name;
      $option->publishable = $publication->role && ($publication->role != "writer");
      $options[$publication->id] = $option;
    }
    return $options;
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
      case 6002:
        $type = "missing-scope";
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

  private static function _encode_ajax_error($e) {
    return json_encode(array(
      "error" => array(
        "message" => $e->getMessage(),
        "code" => $e->getCode()
      )
    ));
  }

  // Requests

  /**
   * Handles the response from a remote request.
   */
  private static function _handle_response($response) {
    $code = wp_remote_retrieve_response_code($response);
    $content_type = wp_remote_retrieve_header($response, "content-type");
    $body = wp_remote_retrieve_body($response);

    if (is_wp_error($response)) {
      throw new Exception($response->get_error_message(), 500);
    }

    if (false === strpos($content_type, "json")) {
      throw new Exception(__("Unexpected response format.", "medium"), $code);
    }

    $payload = json_decode($body);
    if (isset($payload->errors)) {
      $error = $payload->errors[0];
      throw new Exception($error->message, $error->code);
    }

    return $payload->data;
  }
}
