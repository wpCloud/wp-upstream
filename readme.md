WP-Upstream
===========

This plugin will automatically create Git commits for any kind of installation, update or deletion in WordPress (for example updating Core, installing a plugin or deleting a theme).

Prerequisites
-------------

In the WordPress site's root directory, you have to initialize a Git repository and configure it properly. If you want to push, you also have to set a remote.

Settings
-------

The current version does not have a settings interface, but there are a few constants to use:

* `WP_UPSTREAM_GIT_PATH` - define this if you need to override the relative path to the git command (the default is `git`, in most cases it should not be changed)
* `WP_UPSTREAM_REMOTE_NAME` - define this if you need to override the remote name (the default is `origin`, in most cases it should not be changed)
* `WP_UPSTREAM_AUTOMATIC_PUSH` - define this with boolean `true` if you always want your commits to be pushed immediately (if you do this, make sure to set a remote repository properly; the plugin currently does not check if a push is successful or not)
* `WP_UPSTREAM_DEBUG` - define this with boolean `true` if you want a log of Git commands to be created

Settings
--------
Update wp-config.php with the following to push updates automatically.

```
define( 'WP_UPSTREAM_AUTOMATIC_PUSH', true );
```
