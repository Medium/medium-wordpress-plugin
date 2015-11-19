=== Medium ===
Contributors: mediumdotcom, majelbstoat
Tags:  medium, medium auto publish, publish post to medium, medium publishing, post to medium, social media auto publish, social media publishing, social network auto publish, social media, social network
Requires at least: 3.3
Tested up to: 4.3
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

= Medium 1.1 =
* New: Publish a post directly into a publication.
* New: Posts are sent to Medium with the same publish date as on WordPress.
* New: Optionally prevent your Medium followers from being notified of the published post.
* Fix: No longer show cross-links to Medium if you didn't want them. (Note to self: "no" is not falsey)
* Change: Use WP's own remote request library to maximise compatibility with different server configurations.
* i18n: German translation, contributed by https://github.com/lsinger

= Medium 1.0 =
* Initial release!

= Requirements =

* WordPress 3.3+
* PHP 5.2.4+
