=== AI H5P Generator ===
Contributors: thirzel
Tags: h5p, ai, mistral, quiz
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later

Generate H5P content (quizzes, multiple choice, fill-in-the-blanks) with the Mistral API directly from the WordPress admin.

== Description ==

AI H5P Generator adds a top-level admin menu where you describe the content you
want (e.g. "a 5-question quiz about photosynthesis") and it uses the Mistral API
to generate an H5P file that is saved to your media library, ready to be
imported into the H5P plugin or downloaded.

Your Mistral API key is configured on the plugin's Settings page — no file
access on the server is required.

Generated content is stored under `wp-content/uploads/aiah5p/{id}/` and can be
embedded in any post or page with the `[aiah5p id="…"]` shortcode (rendered
with the h5p-standalone player), downloaded as a `.h5p` file for use with the
official H5P plugin, or deleted — all from the plugin's Content page.

== Installation ==

1. Upload the `ai-h5p-generator` folder to `wp-content/plugins/`, or upload
   the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to AI H5P → Settings and enter your Mistral API key.
4. Go to AI H5P → Generate and create your first H5P content.

Architecture follows the official H5P plugin's model: libraries are stored once
under `wp-content/uploads/aiah5p/libraries/{Machine}-{major.minor}/` and
deduplicated by name+version. Content metadata lives in custom database tables
(`wp_aiah5p_libraries`, `wp_aiah5p_contents`, `wp_aiah5p_contents_libraries`)
with a dependency graph per content item. When generating content, the plugin
resolves the full dependency tree (e.g. QuestionSet → MultiChoice), stores the
mapping, and only bundles exactly those libraries into the exported `.h5p`
file. The front-end player loads libraries from the shared directory, so
nothing is copied per content item. Libraries that are in use by content
cannot be deleted. Add libraries via the **Libraries** page by uploading any
example `.h5p` or `.zip` file from h5p.org.

== Manual creation and editing ==

The **Create** page lists every installed runnable library; choose one to
create new H5P content from scratch. Every content item — whether created
manually or generated with AI — is editable via **Content → Edit**, where the
title and the parameters (content JSON) can be changed; updates are written
back to the database and the content files, and the shortcode immediately
reflects the changes. The editor validates the JSON before saving and
restores your draft after an error.

== Changelog ==

= 0.1.0 =
* Initial release: Mistral-powered generation of QuestionSet, MultiChoice and
  Fill in the Blanks content, admin UI with top-level menu, settings page,
  `[aiah5p]` shortcode rendering via h5p-standalone, and a Content page with
  download/delete actions.
* Joubel-style architecture: shared libraries with a database dependency
  graph, dependency-resolved `.h5p` exports, libraries protected while in use.
* Manual creation from scratch (Create page) and editing of every content item
  (Edit page) with JSON validation and draft restore.
