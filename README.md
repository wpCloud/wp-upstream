WP-Upstream
===========

This plugin will automatically create Git commits for any kind of installation, update or deletion in WordPress (for example updating Core, installing a plugin or deleting a theme).

Prerequisites
-------------

In the WordPress site's root directory, you have to initialize a Git repository and configure it properly. If you want to push, you also have to set a remote.

Options
-------

The current version does not have a settings interface, but there are a few constants to use:

* `WPUPSTREAM_GIT_PATH` - define this if you need to override the relative path to the git command (the default is just `git`)
* `WPUPSTREAM_AUTOMATIC_PUSH` - define this with boolean `true` if you always want your commits to be pushed immediately (if you do this, make sure to set a remote repository properly; the plugin currently does not check if a push is successful or not)
* `WPUPSTREAM_DEBUG` - define this with boolean `false` if you don't want a log of Git commands to be created
