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

To bundle H5P libraries, place them under `ai-h5p-generator/h5p-libraries/`
(e.g. `h5p-libraries/H5P.QuestionSet/`, `h5p-libraries/H5P.MultiChoice/`,
`h5p-libraries/H5P.Blanks/`) — each folder containing the library's
`library.json`, `semantics.json`, `previews/`, `scripts/`, `styles/` etc. as
found in the official H5P releases. After that, generated `.h5p` files are
complete and can be imported into the H5P plugin.

== Changelog ==

= 0.1.0 =
* Initial release: Mistral-powered generation of QuestionSet, MultiChoice and
  Fill in the Blanks content, admin UI with top-level menu, settings page,
  `[aiah5p]` shortcode rendering via h5p-standalone, and a Content page with
  download/delete actions.
