=== Post to Convex ===
Contributors: beddev
Tags: convex, sync, rest-api, block-editor, posts
Requires at least: 6.7
Tested up to: 6.9.4
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress posts to a Convex backend from the Gutenberg block editor (not the Classic Editor), with encrypted credentials and a server-side REST proxy.

== Description ==

Post to Convex connects your WordPress site to a [Convex](https://www.convex.dev/) deployment. Editors can push, update, or remove posts in Convex from the **Gutenberg (block) editor** sidebar. The plugin does **not** support the Classic Editor: there is no meta box or toolbar there, and post content is translated from block markup only. The plugin stores connection settings in WordPress, encrypts the shared secret at rest, and proxies outbound API calls through WordPress so the secret never reaches the browser.

= Features =

* **Gutenberg sidebar** — Post to Convex, update an existing sync, or remove a post from Convex from the block editor only. Unsaved edits must be saved before syncing. Not available in the Classic Editor.
* **Admin settings** — Configure your Convex Cloud URL and shared secret under **Settings → Post to Convex Settings**.
* **Encrypted secret storage** — The Convex secret is stored with AES-256-GCM; key material is derived from WordPress salts in `wp-config.php`, not from the database.
* **REST API proxy** — Authenticated routes under `post-to-convex/v1` load post data from WordPress and forward it to your Convex HTTP API.
* **Post meta** — After a successful create, the remote document id is saved in `post_to_convex_remote_id` and exposed to the REST API for the editor.
* **Media sync** — Image attachments (JPEG, PNG, WebP, GIF) upload to Convex automatically from the media library or when set as a featured image. Editing attachment metadata (alt, title, caption, description) in the media library PATCHes Convex when `post_to_convex_media_id` is already set. Deleting an attachment removes it from Convex. Post sync includes `featuredImageMediaId` and uploads an unsynced featured image via `ensure_attachment_synced`.

= Requirements =

* A Convex deployment that exposes the Post to Convex HTTP API (see **Convex backend** below).
* **Gutenberg (block editor)** for manual post sync. Sites that use the Classic Editor only are not supported for post sync (see FAQ).
* PHP 8.2+ with OpenSSL enabled (for secret encryption).
* PHP **cURL** extension with `curl_init` and `CURLFile` support (required for media uploads; see **Why media uploads use cURL** below). Post and taxonomy sync use `wp_remote_request` and still need WordPress’s usual HTTP transport.
* Users who sync content need the `edit_posts` capability; changing settings requires `manage_options`.

Environments without the cURL extension are **not supported**. Media will not upload to Convex; failures are logged to the PHP error log.

= Convex backend =

Your Convex app should accept authenticated requests at:

`{CONVEX_CLOUD_URL}/api/postToConvex/v1/posts`

* **Create / update** — `POST` with a JSON body (title, slug, content, excerpt, type, status, dates, `originalId`, `authorId`, and on update `_id` from WordPress meta).
* **Delete** — `DELETE` with JSON `{ "_id": "<remote id>" }`.

Media endpoint: `{CONVEX_CLOUD_URL}/api/postToConvex/v1/media`

* **Upload** — `PUT` with `multipart/form-data` (`file` plus optional `alt`, `title`, `caption`, `description`). Allowed types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`. Response: `{ "mediaId": "..." }`. WordPress sends this request with **native PHP cURL** and `CURLFile`, not `wp_remote_request`.
* **Update metadata** — `PATCH` with JSON `{ "mediaId", "alt", "title", "caption", "description" }` (all required strings; use `""` when empty). Only updates metadata on an existing row; requires `post_to_convex_media_id` in WordPress. Sent via `wp_remote_request`.
* **Delete** — `DELETE` with JSON `{ "mediaId": "<id>" }` (via `wp_remote_request`).

Set the environment variable `POST_TO_CONVEX_SECRET` in Convex to the same value you save in WordPress. The plugin sends it as a `Bearer` token on outbound requests.

= Why media uploads use cURL =

Convex media uploads are large `multipart/form-data` **PUT** requests. The plugin does **not** use WordPress `wp_remote_request()` for uploads because, in practice, building the entire file into a string and sending it through the HTTP API layer was unreliable: transfers failed with cURL error 18 (“transfer closed with outstanding read data remaining”), HTTP/2 stream resets, or gateway errors on multi‑megabyte images—even when `Content-Length` and `Expect` headers were set correctly.

Instead, `MediaSync` uses PHP’s cURL API with `CURLFile` so libcurl reads the attachment from disk, builds a valid multipart body, and can force **HTTP/1.1** (`CURLOPT_HTTP_VERSION`). That combination matches how file uploads are expected to work and is what this plugin tests against.

Post sync, taxonomy sync, and media **PATCH** / **DELETE** continue to use `wp_remote_request()` with JSON bodies; only media **upload** requires cURL.

= PHP components =

The plugin loads PHP classes via Composer PSR-4 autoloading (`PostToConvex` namespace):

* `PostToConvex\AdminSettings` — Options page, `post_to_convex_cloud_url` and `post_to_convex_secret` options.
* `PostToConvex\SecretStore` — Encrypt and decrypt the shared secret (`encrypt`, `decrypt`, `get_plaintext_secret`).
* `PostToConvex\PostMeta` — Registers `post_to_convex_remote_id` for REST-enabled post types.
* `PostToConvex\AttachmentMeta` — Registers `post_to_convex_media_id` on attachments.
* `PostToConvex\MediaSync` — Uploads, PATCHes metadata, and deletes media in Convex on attachment hooks.
* `PostToConvex\RestApi` — Registers proxy routes and handlers.
* `PostToConvex\Blocks` — Registers block metadata and block-editor assets (`build/editor.js` sidebar).

= REST routes =

Namespace: `post-to-convex/v1` (full base: `/wp-json/post-to-convex/v1/`).

Permission: callers must have `edit_posts`.

**Create or update**

* Route: `POST /createOrUpdatePostServer`
* Body: `{ "id": <post id>, "isUpdate": <boolean> }`
* Loads the post from the database, forwards fields to Convex, and on first sync stores the returned id in post meta.

**Remove**

* Route: `DELETE /removePostServer`
* Body: `{ "id": <post id> }`
* Deletes the remote document using `post_to_convex_remote_id`, then clears that meta key.

== Installation ==

1. Upload the `post-to-convex` folder to `/wp-content/plugins/`.
2. Activate **Post to Convex** under **Plugins** in WordPress.
3. Go to **Settings → Post to Convex Settings**.
4. Enter your **Convex Cloud URL** (base URL of the deployment, without the `/api/postToConvex/v1/posts` path — the plugin appends that).
5. Generate a shared secret (for example `openssl rand -hex 32`), paste it into **Convex secret**, and click **Save Changes**.
6. In the Convex dashboard, add an environment variable `POST_TO_CONVEX_SECRET` with the same value.
7. Open a post in the block editor, open the **Post to Convex** sidebar, save the post, then use **Post to Convex** to sync.

== Frequently Asked Questions ==

= Does this work with the Classic Editor? =

No. Post sync is built for the **Gutenberg block editor** only. The “Post to Convex” panel is registered as a block-editor plugin sidebar (`build/editor.js`), and exported `content` is produced by translating Gutenberg blocks (heading, paragraph, list, and nested blocks)—not Classic Editor HTML. Use the block editor for posts you intend to sync, or install a plugin that disables the block editor only if you accept that this workflow will not apply.

Category and tag sync (admin taxonomy screens) and automatic media upload (media library) do not depend on which post editor you use.

= Why must I save the post before syncing? =

The sidebar disables sync while the post has unsaved changes so WordPress sends the latest saved content to Convex, not stale editor state.

= What happens if I leave the secret field blank when saving settings? =

The existing encrypted secret is kept unchanged.

= Where is the Convex document id stored? =

In post meta key `post_to_convex_remote_id`, readable in the editor when you have permission to edit the post.

= Where is the Convex media id stored? =

On attachment posts, in meta key `post_to_convex_media_id`. Post sync sends `featuredImageMediaId` from that meta when the post has a featured image, and uploads the featured image first when the meta is missing. Editing attachment metadata alone does not upload pre-existing library images; only uploads, featured-image hooks, or post sync create Convex rows.

= Does the plugin work without the cURL extension? =

No. Media upload to Convex requires `curl_init` and `CURLFile`. Without them, uploads are skipped and an error is written to the PHP error log. Install or enable the PHP cURL extension (common on standard WordPress hosting).

== Changelog ==

= 0.1.0 =
* Initial release: admin settings, encrypted secret storage, REST proxy, block editor sidebar, and remote id post meta.

== Upgrade Notice ==

= 0.1.0 =
First public release.
