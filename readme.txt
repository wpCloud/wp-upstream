=== WP Upstream ===

Plugin Name:       WP Upstream
Plugin URI:        http://wordpress.org/plugins/wp-upstream/
Author URI:        https://www.usabilitydynamics.com/
Author:            Usability Dynamics Inc.
Contributors:      usability_dynamics, flixos90, andypotanin, Anton Korotkoff
Requires at least: 4.0 
Tested up to:      4.9.6
Stable tag:        0.1.8
Version:           0.1.8
License:           GPL v2 
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Tags:              git, automization, updates, version management, commit, push, svn

This plugin will automatically create Git commits for any kind of installation, update or deletion in WordPress.

== Description ==

WP Upstream helps you manage your WordPress projects with Git. If you have your complete site hosted on Github, Bitbucket or any other Git repository hosting service, you don't need to worry about manually creating additional commits for plugin, theme or core updates. WP Upstream handles this for you automatically, also doing an automatic push if you like.

WP Upstream listens to your actions within the WordPress admin and will trigger a commit any time you:
* install a plugin or theme
* update core, a plugin or theme
* bulk update plugins or themes
* delete a plugin or a theme

= Prerequisites =

In order for the plugin to work, you need to set up a Git repository in your WordPress site's root directory and configure it properly. Also make sure PHP has the permissions to perform actions on the command line using the `exec()` function.

= Settings =

Since WP Upstream is targeted at developers, there is currently no settings screen for this plugin. However there are a few settings that can be configured using constants:

* `WP_UPSTREAM_GIT_PATH` - define this if you need to override the relative path to the git command (the default is `git`, in most cases it should not be changed)
* `WP_UPSTREAM_REMOTE_NAME` - define this if you need to override the remote name (the default is `origin`, in most cases it should not be changed)
* `WP_UPSTREAM_AUTOMATIC_PUSH` - define this with boolean `true` if you always want your commits to be pushed immediately (if you do this, make sure to set a remote repository properly; the plugin currently does not check if a push is successful or not)
* `WP_UPSTREAM_DEBUG` - define this with boolean `true` if you want a log of Git commands to be created

**Note:** WP Upstream requires PHP 5.3.

== Installation ==

= Download and Activation =

1. Either download the plugin from within your WordPress site, or download it manually and then upload the entire `wp-upstream` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. A new item 'WP Upstream' in the top right of the admin bar will notify you about the current status of WP Upstream.

= Setup Guide =

Set up a new Git repository in your WordPress site's root directory and configure it properly. If you are interested in leveraging the automatic push functionality of the plugin, make sure to add a remote repository to the plugin.

== Frequently Asked Questions ==

= How do I suggest an idea or submit a support request? =

WP Upstream is still in an early development version. If you have an idea to improve the plugin, need support or find a bug, you can send us a message via our website or, preferably, visit our [feedback.usabilitydynamics.com](http://feedback.usabilitydynamics.com/forums/314358-wp-upstream) page.

== Changelog ==

= 0.1.8 =
* fixes to Git automation handling

= 0.1.6 =
* public release build
* switched to PSR-4 autoloader

= 0.1.5 =
* disabled debug mode by default

= 0.1.4 =
* fixed theme deletion on multisite
* improved compatibility with multisite and WP CLI
* theme information is now retrieved from local files
* fixed admin bar menu design
* admin bar menu now has its own class

= 0.1.3 =
* for commits, the display name of the user is now used
* enhanced the admin bar menu of the plugin
* added a class representing a commit and additional related functions
* improved stability by making dynamic Git handler more flexible

= 0.1.2 =
* Fixed author and committer data for git.
* Fixed a bug where deleted files were not added to a commit.
* Changed all constant names to follow WP_UPSTREAM prefix.
* Added admin bar status notice.
* Added changes.md file.
* Made plugin network only.
* Removed PHP 5.2 autoloader.

= 0.1.1 =
* fixed a bug where plugin would stop working after an unfinished process
* added documentation

= 0.1.0 =
* initial release
