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

  private static $_migration_table = "";

  private static $_medium_api_host = "https://api.medium.com";

  /**
   * Initialises actions and filters.
   */
  public static function init() {
    if (self::$_initialised) return;
    self::$_initialised = true;

    session_start();

    add_action("admin_init", array("Medium_Admin", "admin_init"));
    add_action("admin_notices", array("Medium_Admin", "admin_notices"));
    add_action("tool_box", array("Medium_Admin", "tool_box"));

    add_action("show_user_profile", array("Medium_Admin", "show_user_profile"));
    add_action("edit_user_profile", array("Medium_Admin", "show_user_profile"));

    add_action("personal_options_update", array("Medium_Admin", "personal_options_update"));
    add_action("edit_user_profile_update", array("Medium_Admin", "personal_options_update"));

    add_action("wp_ajax_medium_refresh_publications", array("Medium_Admin", "refresh_publications"));
    add_action("wp_ajax_medium_prepare_migration", array("Medium_Admin", "prepare_migration"));
    add_action("wp_ajax_medium_run_migration", array("Medium_Admin", "run_migration"));
    add_action("wp_ajax_medium_reset_migration", array("Medium_Admin", "reset_migration"));

    add_action("add_meta_boxes_post", array("Medium_Admin", "add_meta_boxes_post"));

    add_action("save_post", array("Medium_Admin", "save_post"), 10, 2);
  }

  // Actions and hooks.

  /**
   * Initialises admin functionality.
   */
  public static function admin_init() {
    global $wpdb;
    load_plugin_textdomain("medium");

    wp_register_script("medium_admin_js", MEDIUM_PLUGIN_URL . "js/admin.js");
    wp_localize_script("medium_admin_js", "medium", array(
    	"errorMissingScope" => __("An updated integration token is needed to perform this action. Please create a new integration token from your Medium settings page and set it on your WordPress profile above.", "medium"),
      "errorUnknown" => __("An unknown error occurred (%s).", "medium")
    ));
    wp_enqueue_script("medium_admin_js");

    wp_enqueue_style("medium_admin_css", MEDIUM_PLUGIN_URL . "css/admin.css", array(), MEDIUM_VERSION);

    self::$_migration_table = $wpdb->prefix . "medium_migration";
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
   * Adds migration tools to the tool box
   */
  public static function tool_box() {
    global $current_user;
    global $wpdb;

    self::_ensure_migration_table();

    $medium_user = Medium_User::get_by_wp_id($current_user->ID);
    if (!$medium_user->publications && $medium_user->token) {
      try {
        // Refresh the set of publications the user can contribute to.
        $medium_user->publications = self::get_contributing_publications($medium_user->token, $medium_user->id);
        $medium_user->save($current_user->ID);
      } catch (Exception $e) {
        self::_add_api_error_notice($e, $medium_user->token);
      }
    }

    $migration_data = self::_get_migration_data();
    $migration_data->reset_visibility_class = "hidden";
    $migration_data->run_visibility_class = "";
    if ($migration_data->progress->total) {
      $migration_data->prepare_visibility_class = "hidden";
      $migration_data->execute_visibility_class = "";
      if ($migration_data->progress->total == $migration_data->progress->completed) {
        $migration_data->reset_visibility_class = "";
        $migration_data->run_visibility_class = "hidden";
      }
    } else {
      $migration_data->prepare_visibility_class = "";
      $migration_data->execute_visibility_class = "hidden";
    }

    if ($migration_data->strategy->fallback_user_id) {
      $fallback_medium_user = Medium_User::get_by_wp_id($migration_data->strategy->fallback_user_id);
      $migration_data->strategy->fallback_medium_user_id = $fallback_medium_user->id;
    } else {
      $migration_data->strategy->fallback_medium_user_id = "";
    }

    $user_integration_data = self::_get_user_integration_data();
    Medium_View::render("form-migrate-tool", array(
      "medium_post_statuses" => self::_get_post_statuses(false),
      "medium_post_licenses" => self::_get_post_licenses(),
      "medium_boolean_options" => self::_get_boolean_options(),
      "medium_publication_options" => self::_get_migrate_publication_options($medium_user),
      "fallback_accounts" => $user_integration_data->linked_accounts,
      "migration" => $migration_data,
    ));
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
      $medium_user->username = "";
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
        $medium_user->username = $user->username;

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
    $user_id = $_POST['user_id'];
    if (!current_user_can("edit_user", $user_id)) {
      echo self::_encode_ajax_error(new Exception("You don't have permission to manage this user.", -1));
      die();
    }

    $medium_user = Medium_User::get_by_wp_id($user_id);
    try {
      // Persist the publications for next time.
      $medium_user->publications = self::get_contributing_publications($medium_user->token, $medium_user->id);
      $medium_user->save($user_id);

      echo json_encode(self::_get_user_publication_options($medium_user));
    } catch (Exception $e) {
      echo self::_encode_ajax_error($e);
    }
    die();
  }

  /**
   * Handles the AJAX callback to prepare a migration.
   */
  public static function prepare_migration() {
    global $wpdb;
    $publication_id = $_POST['publication_id'];
    $post_status = $_POST['post_status'];
    $post_license = $_POST['post_license'];
    $fallback_medium_user_id = $_POST['fallback_medium_user_id'];

    if (!$fallback_medium_user_id) {
      echo self::_encode_ajax_error(new Exception("Fallback user must be specified", -1));
      die();
    }

    // Get the medium user data for all users.
    $connected_users = $wpdb->get_results("
      SELECT u.ID as user_id, um.meta_value as medium_user
      FROM $wpdb->users AS u
      INNER JOIN $wpdb->usermeta AS um
      ON u.ID = um.user_id
      WHERE um.meta_key = 'medium_user'
    ");

    $editor_user_ids = array();
    $writer_user_ids = array();
    $fallback_status = "";
    foreach ($connected_users as $connected_user) {
      $medium_user = unserialize($connected_user->medium_user);
      if ($medium_user->publications && array_key_exists($publication_id, $medium_user->publications)) {
        if ($medium_user->publications[$publication_id]->role == "writer") {
          $writer_user_ids[] = $connected_user->user_id;
          if ($medium_user->id == $fallback_medium_user_id) {
            $fallback_status = "draft";
            $fallback_user_id = $connected_user->user_id;
          }
        } else {
          $editor_user_ids[] = $connected_user->user_id;
          if ($medium_user->id == $fallback_medium_user_id) {
            $fallback_status = $post_status;
            $fallback_user_id = $connected_user->user_id;
          }
        }
      }
    }

    if (!$fallback_status) {
      echo self::_encode_ajax_error(new Exception("Fallback user must be an editor or writer for the publication", -1));
      die();
    }

    // Get the post meta data for all posts.
    $posts = $wpdb->get_results("
      SELECT p.ID AS post_id, p.post_author, pm.medium_post
      FROM $wpdb->posts AS p
      LEFT JOIN (
        SELECT post_id, meta_value AS medium_post
        FROM $wpdb->postmeta
        WHERE meta_key = 'medium_post'
      ) AS pm
      ON p.ID = pm.post_id
      WHERE p.post_type = 'post'
      AND p.post_status = 'publish'
    ");

    $connected_count = 0;
    $fallback_count = 0;
    $statuses = array(
      "unlisted" => 0,
      "draft" => 0,
      "public" => 0
    );
    $rows = array();

    // Define a header row that describes the migration strategy.
    $rows[] = "(0, $fallback_user_id, '$publication_id', '$post_status', '$post_license', 0)";

    foreach ($posts as $post) {
      if ($post->medium_post) {
        $medium_post = unserialize($post->medium_post);

        // If it already has an id, this post has already been migrated.
        if ($medium_post->id) continue;
      }

      $fallback = 0;
      if (in_array($post->post_author, $editor_user_ids)) {
        // Post author is an editor of the publication. Use the requested post status.
        $migration_status = $post_status;
        $connected_count++;
      } else if (in_array($post->post_author, $writer_user_ids)) {
        $migration_status = "draft";
        $connected_count++;
      } else {
        $migration_status = $fallback_status;
        $fallback = 1;
        $fallback_count++;
      }
      $statuses[$migration_status]++;

      $rows[] = "({$post->post_id}, {$post->post_author}, '$publication_id', '$migration_status', '$post_license', $fallback)";
    }

    $values = implode(",", $rows);
    $table = self::$_migration_table;

    // Prepare the migration table.
    $wpdb->query("TRUNCATE TABLE $table");

    // Write the prepared data to the migration table.
    $wpdb->query("
      INSERT INTO $table
        (post_id, user_id, medium_publication_id, medium_post_status, medium_post_license, fallback)
      VALUES $values
    ");

    echo json_encode(array(
      "fallbackCount" => $fallback_count,
      "connectedCount" => $connected_count,
      "statuses" => $statuses
    ));
    die();
  }

  /**
   * Performs the migration.
   */
  public static function run_migration() {
    global $wpdb;
    $table = self::$_migration_table;

    $migrations = $wpdb->get_results("
      SELECT *
      FROM $table
      WHERE post_id != 0
      AND medium_post_id IS NULL
      LIMIT 5
    ");

    $fallback = $wpdb->get_row("
      SELECT *
      FROM $table
      WHERE post_id = 0
    ");

    $medium_users_by_wp_id = array();
    $fallback_medium_user = Medium_User::get_by_wp_id($fallback->user_id);
    $medium_users_by_wp_id[$fallback->user_id] = $fallback_medium_user;

    foreach ($migrations as $migration) {
      $post = get_post($migration->post_id);
      $user = get_userdata($migration->user_id);

      $medium_post = Medium_Post::get_by_wp_id($migration->post_id);
      $medium_post->status = $migration->medium_post_status;
      $medium_post->license = $migration->medium_post_license;
      $medium_post->publication_id = $migration->medium_publication_id;
      $medium_post->cross_link = "no";
      $medium_post->follower_notification = "no";
      if ($migration->fallback) {
        $medium_post->byline_name = $user->display_name;
        $medium_post->byline_email = $user->user_email;
        $medium_user = $fallback_medium_user;
      } else {
        if (!array_key_exists($migration->user_id, $medium_users_by_wp_id)) {
          $medium_users_by_wp_id[$migration->user_id] = Medium_User::get_by_wp_id($migration->user_id);
        }
        $medium_user = $medium_users_by_wp_id[$migration->user_id];
      }

      try {
        $created_medium_post = self::cross_post($post, $medium_post, $medium_user);

      } catch (Exception $e) {
        echo self::_encode_ajax_error($e);
        die();
      }

      // Save the newly migrated post data.
      $medium_post->id = $created_medium_post->id;
      $medium_post->url = $created_medium_post->url;
      $medium_post->author_image_url = $medium_user->image_url;
      $medium_post->author_url = $medium_user->url;
      $medium_post->save($migration->post_id);

      // Mark this post as migrated.
      $wpdb->query("
        UPDATE $table
        SET medium_post_id = '{$medium_post->id}'
        WHERE post_id = {$migration->post_id}
      ");
    }

    echo json_encode(array(
      "migrated" => count($migrations)
    ));
    die();
  }

  /**
   * Drops migration data.
   */
  public static function reset_migration() {
    global $wpdb;
    $table = self::$_migration_table;

    $wpdb->query("TRUNCATE TABLE $table");
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
      "medium_user" => $medium_user,
      "user_id" => $user->ID
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
        $medium_post->publication_id = $medium_user->default_publication_id ? $medium_user->default_publication_id : NO_PUBLICATION;
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
          // Override role if the role is editor to promote role
          // Fixes "Draft Only" issue when user is both an editor and a writer
          if (array_key_exists($publication->id, $contributing_publications)) {
            if ($contributor->role == "editor") {
              $contributing_publications[$publication->id]->role = "editor";
            } else {
              continue;
            }
          }
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
   * Returns information on the integration status of user accounts.
   */
  private static function _get_user_integration_data() {
    global $wpdb;
    $user_integrations = $wpdb->get_results("
      SELECT u.ID as user_id, u.display_name AS name, um.medium_user
      FROM $wpdb->users AS u
      LEFT JOIN (
        SELECT user_id, meta_value AS medium_user FROM $wpdb->usermeta AS um
        WHERE um.meta_key = 'medium_user'
      ) AS um
      ON u.ID = um.user_id
    ");

    $unlinked_accounts = array();
    $linked_accounts = array();
    foreach ($user_integrations as $user_integration) {
      if ($user_integration->medium_user) {
        $migration_user = unserialize($user_integration->medium_user);
        if ($migration_user->token) {
          $linked_accounts[$migration_user->id] = $user_integration->name . ' (@' . $migration_user->username . ')';
          continue;
        }
      }
      $unlinked_accounts[$user_integration->user_id] = $user_integration->name;
    }

    $result = new stdClass();
    $result->unlinked_accounts = $unlinked_accounts;
    $result->linked_accounts = $linked_accounts;

    return $result;
  }

  /**
   * Ensures that the migration table exists.
   */
  private static function _ensure_migration_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = self::$_migration_table;

    // Create the migration table if necessary.
    $wpdb->query("
      CREATE TABLE IF NOT EXISTS $table (
        post_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        medium_publication_id varchar(32) NOT NULL,
        medium_post_status varchar(32) NOT NULL,
        medium_post_license varchar(32) NOT NULL,
        medium_post_id varchar(32) NULL,
        fallback tinyint NOT NULL,
        PRIMARY KEY  (post_id, user_id),
        KEY medium_post_id (medium_post_id)
      ) $charset_collate
    ");
  }

  /**
   * Returns information on current migration progress
   */
  private static function _get_migration_data() {
    global $wpdb;
    $table = self::$_migration_table;

    $migration = new stdClass();

    // Retrive progress data.
    $migration->progress = $wpdb->get_row("
      SELECT SUM(CASE WHEN medium_post_id IS NULL THEN 0 ELSE 1 END) AS completed,
        SUM(CASE WHEN medium_post_status = 'draft' THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN medium_post_status = 'unlisted' THEN 1 ELSE 0 END) AS unlisted,
        SUM(CASE WHEN medium_post_status = 'public' THEN 1 ELSE 0 END) AS public,
        SUM(CASE WHEN fallback = 1 THEN 1 ELSE 0 END) AS fallback,
        SUM(CASE WHEN fallback = 0 THEN 1 ELSE 0 END) AS connected,
        COUNT(*) AS total
      FROM $table
      WHERE post_id != 0
    ");

    // Retrieve the migration strategy data.
    $strategy_data = $wpdb->get_row("
      SELECT user_id AS fallback_user_id, medium_publication_id, medium_post_status, medium_post_license
      FROM $table
      WHERE post_id = 0;
    ");
    if (!$strategy_data) {
      $strategy_data = new stdClass();
      $strategy_data->fallback_user_id = "";
      $strategy_data->medium_publication_id = "";
      $strategy_data->medium_post_status = "";
      $strategy_data->medium_post_license = "";
    }
    $migration->strategy = $strategy_data;

    return $migration;
  }

  // API calls.

  /**
   * Creates a post on Medium.
   */
  public static function cross_post($post, $medium_post, $medium_user) {
    $tag_data = wp_get_post_terms($post->ID, array("post_tag", "slug"));
    // Use wp_get_post_tags() if WP_Error or empty array returned
    if (!count($tag_data) || is_wp_error($tag_data)) {
      $tag_data = wp_get_post_tags($post->ID);
    }
    $tags = array();
    $slugs = array();
    foreach ($tag_data as $tag) {
      if ($tag->taxonomy == "post_tag") {
        $tags[] = $tag->name;
      } elseif ($tag->taxonomy == "slug") {
        // For installations that have the custom taxonomy "slug", ensure that
        // these are are the head of the tag list.
        $slugs[] = $tag->name;
      }
    }
    $tags = array_values(array_unique(array_merge($slugs, $tags)));

    if (class_exists('CoAuthors_Guest_Authors')) {
      // Handle guest-authors if the CoAuthors Plus plugin is installed.
      $coauthors = get_coauthors($post->ID);
      $primary_author = $coauthors[0];
      if ($primary_author->type == "guest-author") {
        $medium_post->byline_name = $primary_author->display_name;
        $medium_post->byline_email = $primary_author->user_email;
      }
    }

    $permalink = get_permalink($post->ID);
    $content = Medium_View::render("content-rendered-post", array(
      "title" => strip_tags($post->post_title),
      "content" => self::_prepare_content($post),
      "cross_link" => $medium_post->cross_link == "yes",
      "site_name" => get_bloginfo('name'),
      "permalink" => $permalink,
      "byline" => $medium_post->byline_name
    ), true);

    $body = array(
      "title" => strip_tags($post->post_title),
      "content" => $content,
      "tags" => $tags,
      "contentFormat" => "html",
      "canonicalUrl" => $permalink,
      "license" => $medium_post->license,
      "publishStatus" => $medium_post->status,
      "publishedAt" => mysql2date('c', isset($post->post_date_gmt) ? $post->post_date_gmt : $post->post_date),
      "notifyFollowers" => $medium_post->follower_notification == "yes"
    );
    $data = json_encode($body);

    if ($medium_post->publication_id != NO_PUBLICATION) {
      $path = "/v1/publications/{$medium_post->publication_id}/posts";
    } else {
      $path = "/v1/users/{$medium_user->id}/posts";
    }

    try {
      $created_medium_post = self::_medium_request("POST", $path, $medium_user->token, $data, array(
        "Content-Type" => "application/json"
      ));
    } catch (Exception $e) {
      // Retry on the server failure response codes
      $retry_response_codes = array(
        500,
        502,
        503,
        504
      );

      // Retry once for timeout or server error
      if ($e->getCode() == -2) {
        error_log("RETRYING POST $post->ID '$post->post_title' due to timeout, delaying...");
        sleep(5);
      } else if (in_array($e->getCode(), $retry_response_codes)) {
        error_log("RETRYING POST $post->ID '$post->post_title' due to response code $code, delaying...");
        sleep(5);
      } else {
        throw $e;
      }

      $created_medium_post = self::_medium_request("POST", $path, $medium_user->token, $data, array(
          "Content-Type" => "application/json"
      ));
    }
    $medium_post->id = $created_medium_post->id;

    // Don't derail the migration just because of a claims failure
    try {
      if ($medium_post->byline_email) {
        // Create a claim for the post, if necessary.
        self::create_post_claim($medium_post, $medium_user, $medium_post->byline_email);
      }
    } catch (Exception $e) {
      error_log("ERROR: Claim call failed $e->getMessage(), $e->getCode()");
    }

    return $created_medium_post;
  }

  /**
   * Creates a post claim for the original author of a post.
   */
  public static function create_post_claim(Medium_Post $medium_post, Medium_User $medium_user, $email) {
    $body = array(
      "md5" => md5(strtolower($email))
    );
    $data = json_encode($body);
    return self::_medium_request("PUT", "/v1/posts/{$medium_post->id}/author", $medium_user->token, $data, array(
      "Content-Type" => "application/json"
    ));
  }

  /**
   * Gets the user's publications on Medium.
   */
  public static function get_publications($integration_token, $medium_user_id) {
    return self::_medium_request("GET", "/v1/users/$medium_user_id/publications", $integration_token);
  }

  /**
   * Gets a publication's contributors.
   */
  public static function get_publication_contributors($integration_token, $publication_id) {
    return self::_medium_request("GET", "/v1/publications/$publication_id/contributors", $integration_token);
  }

  /**
   * Gets the Medium user's profile information.
   */
  public static function get_medium_user_info($integration_token) {
    return self::_medium_request("GET", "/v1/me", $integration_token);
  }

  // Data.

  /**
   * Returns an array of the valid post statuses.
   */
  private static function _get_post_statuses($include_none = true) {
    $options = array(
      "public" => __("Public", "medium"),
      "draft" => __("Draft", "medium"),
      "unlisted" => __("Unlisted", "medium")
    );
    if ($include_none) {
      $options = array_merge(array(
        "none" => __("None", "medium")
      ), $options);
    }
    return $options;
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
   * Returns an array of publications that are valid migration targets.
   */
  private static function _get_migrate_publication_options(Medium_User $medium_user) {
    $options = array();
    foreach ($medium_user->publications as $publication) {
      $options[$publication->id] = $publication->name;
    }
    return $options;
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
    // If Valenti theme is installed, check for audio or video featured image embeds.
    // Prepend the embed to the post_content when preparing the content.
    // See: http://themeforest.net/item/valenti-wordpress-hd-review-magazine-news-theme/5888961
    if (function_exists('cb_featured_image')) {
      $iframe_url = NULL;
      if (get_post_format($post->post_id) == 'video') {
        $iframe_url = get_post_meta($post->ID, 'cb_video_embed_code_post', true);
      }
      if (get_post_format($post->post_id) == 'audio') {
        $iframe_url = get_post_meta($post->ID, 'cb_soundcloud_embed_code_post', true);
      }
      if (isset($iframe_url)) {
        $post->post_content = sprintf('%s<br />%s', $iframe_url, $post->post_content);
      }
    }

    if (function_exists('has_post_thumbnail') && has_post_thumbnail($post)) {
      // If $post->post_content starts with an <img> tag, do not use the featured image
      if (strpos($post->post_content, "<img") !== 0) {
        $post_content = sprintf('<img src="%s" /><br />%s', get_the_post_thumbnail_url($post, 'full'), do_shortcode(wpautop($post->post_content)));
      } else {
        $post_content = do_shortcode(wpautop($post->post_content));
      }
    } else {
      $post_content = do_shortcode(wpautop($post->post_content));
    }

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
   * Makes a request to Medium's API.
   */
  private static function _medium_request($method, $path, $integration_token, $body = "", $additional_headers = array()) {
    $headers = array_merge(array(
      "Authorization" => "Bearer " . $integration_token,
      "Accept" => "application/json",
      "Accept-Charset" => "utf-8"
    ), $additional_headers);

    $payload = array(
      "headers" => $headers,
      "timeout" => 40,
      "user-agent" => "MonkeyMagic/1.0"
    );
    $url = self::$_medium_api_host . $path;

    error_log("Making request $method $url: $body");
    if ($method == "POST") {
      $payload["body"] = $body;
      $response = wp_remote_post($url, $payload);
    } elseif ($method == "GET") {
      $response = wp_remote_get($url, $payload);
    } elseif ($method == "PUT") {
      $payload["method"] = "PUT";
      $payload["body"] = $body;
      $response = wp_remote_request($url, $payload);
    } else {
      throw new Exception(__("Invalid method specified.", "medium"));
    }

    return self::_handle_response($response);
  }

  /**
   * Handles the response from a remote request.
   */
  private static function _handle_response($response) {
    $code = wp_remote_retrieve_response_code($response);
    $content_type = wp_remote_retrieve_header($response, "content-type");
    $body = wp_remote_retrieve_body($response);

    error_log("Received response ($code - $content_type): $body");
    if (is_wp_error($response)) {
      $message = $response->get_error_message();
      $error_code = $response->get_error_code();
      error_log("WP ERROR: $message ($error_code)");
      if ($error_code == "http_request_failed" && strpos($message, "timed out") !== false) {
        throw new Exception($message, -2); // our custom code for timeouts
      }
      throw new Exception($message, $code);
    }

    if (false === strpos($content_type, "json")) {
      throw new Exception(sprintf(__("Unexpected response format: %s - %s", "medium"), $content_type, $body), $code);
    }

    $payload = json_decode($body);
    if (isset($payload->errors)) {
      $error = $payload->errors[0];
      error_log("API ERROR: $error->message ($error->code)");
      throw new Exception($error->message, $error->code);
    }

    return $payload->data;
  }
}
