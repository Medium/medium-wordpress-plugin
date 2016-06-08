=== Medium ===
Contributors: mediumdotcom, majelbstoat, huckphin
Tags:  medium, medium auto publish, publish post to medium, medium publishing, post to medium, social media auto publish, social media publishing, social network auto publish, social media, social network
Requires at least: 3.3
Tested up to: 4.4
Stable tag: trunk
License: Apache

Publish posts automatically to a Medium profile.

== Description ==

Medium lets you publish posts automatically to a Medium profile.

= About =

Medium is developed and maintained by [Medium](https://medium.com/ "medium.com"). For support, contact us at [yourfriends@medium.com](mailto://yourfriends@medium.com).

== Installation ==

1. Extract `medium.zip` to your `/wp-content/plugins/` directory.
2. In the admin panel under plugins, activate Medium.
3. Add an Integration Token on your profile page.
4. Were you expecting more steps? Sorry to disappoint!

== Frequently Asked Questions ==

= Can I edit posts once I've published them to Medium? =

Any modifications you make to a post after you have sent the post to Medium will not be reflected on Medium. Editing of posts is not yet supported in the Medium API, but may be in the future.

== Screenshots ==

1. The additional profile settings where you can specify default cross-posting behaviour.
2. The cross-post dialog which is visible on the new post page.

== Changelog ==

= Medium 1.4.0 =
* New: Added support for featured image embeds from Valenti themes
* Changed: Retry post creation on server response code failure
* Changed: Get full image for featured images
* Fixed: Bug when checking if posts starts with <img>

= Medium 1.3.1 =
* Fixed: Tag retrieval
* Fixed: Cases that resulted in duplicate images.

= Medium 1.3 =
* New: Featured images
* Changed: Added fallback to post date when GMT time not set.
* Changed: Added editor privileges to users that are both writer/editor of publication.

= Medium 1.2.2 =
* Fixed: Cross posting in timezones ahead of GMT
* Changed: More details in exception message

= Medium 1.2.1 =
* Fixed: Increase Medium request timeout
* Fixed: Strip HTML tags from post titles when cross-posting

= Medium 1.2 =
* Fixed: Shortcodes are now processed before sending to Medium.
* Changed: Restructured remote API calling code.
* New: Tool to migrate posts to Medium.

= Medium 1.1.1 =
* Fixed: Missing publication Id for publishing after upgrade.
* Fixed: Make upgrading safer if WordPress sandboxing doesn't catch activation errors.

= Medium 1.1 =
* New: Publish a post directly into a publication.
* New: Posts are sent to Medium with the same publish date as on WordPress.
* New: Optionally prevent your Medium followers from being notified of the published post.
* Fix: No longer show cross-links to Medium if you didn't want them. (Note to self: "no" is not falsey)
* Changed: Use WP's own remote request library to maximise compatibility with different server configurations.
* i18n: German translation, contributed by https://github.com/lsinger

= Medium 1.0 =
* Initial release!

= Requirements =

* WordPress 3.3+
* PHP 5.2.4+
