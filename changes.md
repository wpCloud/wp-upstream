#### 0.1.8
* Fixed problem that wp-upstream constantly thinking their is an updated version.

#### 0.1.7
* Added automatic updates from latest build.
* Enabled [WP_UPSTREAM_AUTOMATIC_PUSH] by default.

#### 0.1.6
* public release build
* switched to PSR-4 autoloader

#### 0.1.5
* disabled debug mode by default

#### 0.1.4
* fixed theme deletion on multisite
* improved compatibility with multisite and WP CLI
* theme information is now retrieved from local files
* fixed admin bar menu design
* admin bar menu now has its own class

#### 0.1.3
* for commits, the display name of the user is now used
* enhanced the admin bar menu of the plugin
* added a class representing a commit and additional related functions
* improved stability by making dynamic Git handler more flexible

#### 0.1.2
* Fixed author and committer data for git.
* Fixed a bug where deleted files were not added to a commit.
* Changed all constant names to follow WP_UPSTREAM prefix.
* Added admin bar status notice.
* Added changes.md file.
* Made plugin network only.
* Removed PHP 5.2 autoloader.

#### 0.1.1
* fixed a bug where plugin would stop working after an unfinished process
* added documentation

#### 0.1.0
* initial release
