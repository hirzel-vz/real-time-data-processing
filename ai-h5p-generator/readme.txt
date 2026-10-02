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

Upload H5P libraries via the plugin's **Libraries** page: download any
example `.h5p` file from h5p.org and upload it — the plugin extracts all
libraries it contains into its shared library directory. All installed
libraries are bundled into every generated H5P file, so dependencies (e.g.
QuestionSet needs MultiChoice) always travel together. Libraries can also be
added or removed manually under `ai-h5p-generator/h5p-libraries/`, one folder
per library (`H5P.QuestionSet/`, `H5P.MultiChoice/`, ...), each with its
`library.json`, `semantics.json`, `scripts/`, `styles/` etc.

== Changelog ==

= 0.1.0 =
* Initial release: Mistral-powered generation of QuestionSet, MultiChoice and
  Fill in the Blanks content, admin UI with top-level menu, settings page,
  `[aiah5p]` shortcode rendering via h5p-standalone, and a Content page with
  download/delete actions.
