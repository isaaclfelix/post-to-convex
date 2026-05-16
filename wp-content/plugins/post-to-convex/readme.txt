=== Post to Convex ===
Contributors: beddev
Tags: convex, sync, rest-api, block-editor, posts
Requires at least: 6.7
Tested up to: 6.9.4
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress posts to a Convex backend from the block editor, with encrypted credentials and a server-side REST proxy.

== Description ==

Post to Convex connects your WordPress site to a [Convex](https://www.convex.dev/) deployment. Editors can push, update, or remove posts in Convex from the block editor sidebar. The plugin stores connection settings in WordPress, encrypts the shared secret at rest, and proxies outbound API calls through WordPress so the secret never reaches the browser.

= Features =

* **Block editor sidebar** — Post to Convex, update an existing sync, or remove a post from Convex. Unsaved edits must be saved before syncing.
* **Admin settings** — Configure your Convex Cloud URL and shared secret under **Settings → Post to Convex Settings**.
* **Encrypted secret storage** — The Convex secret is stored with AES-256-GCM; key material is derived from WordPress salts in `wp-config.php`, not from the database.
* **REST API proxy** — Authenticated routes under `post-to-convex/v1` load post data from WordPress and forward it to your Convex HTTP API.
* **Post meta** — After a successful create, the remote document id is saved in `post_to_convex_remote_id` and exposed to the REST API for the editor.

= Requirements =

* A Convex deployment that exposes the Post to Convex HTTP API (see **Convex backend** below).
* PHP 8.2+ with OpenSSL enabled (for secret encryption).
* Users who sync content need the `edit_posts` capability; changing settings requires `manage_options`.

= Convex backend =

Your Convex app should accept authenticated requests at:

`{CONVEX_CLOUD_URL}/api/postToConvex/v1/posts`

* **Create / update** — `POST` with a JSON body (title, slug, content, excerpt, type, status, dates, `originalId`, `authorId`, and on update `_id` from WordPress meta).
* **Delete** — `DELETE` with JSON `{ "_id": "<remote id>" }`.

Set the environment variable `POST_TO_CONVEX_SECRET` in Convex to the same value you save in WordPress. The plugin sends it as a `Bearer` token on outbound requests.

= PHP components =

The plugin bootstraps these classes from `post-to-convex.php`:

* `Post_To_Convex_Admin_Settings` — Options page, `post_to_convex_cloud_url` and `post_to_convex_secret` options.
* `Post_To_Convex_Secret_Store` — Encrypt and decrypt the shared secret (`encrypt`, `decrypt`, `get_plaintext_secret`).
* `Post_To_Convex_Post_Meta` — Registers `post_to_convex_remote_id` for REST-enabled post types.
* `Post_To_Convex_Rest_Api` — Registers proxy routes and handlers.
* `Post_To_Convex_Blocks` — Registers block metadata and block-editor assets (`build/editor.js` sidebar).

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

= Why must I save the post before syncing? =

The sidebar disables sync while the post has unsaved changes so WordPress sends the latest saved content to Convex, not stale editor state.

= What happens if I leave the secret field blank when saving settings? =

The existing encrypted secret is kept unchanged.

= Where is the Convex document id stored? =

In post meta key `post_to_convex_remote_id`, readable in the editor when you have permission to edit the post.

== Changelog ==

= 0.1.0 =
* Initial release: admin settings, encrypted secret storage, REST proxy, block editor sidebar, and remote id post meta.

== Upgrade Notice ==

= 0.1.0 =
First public release.
