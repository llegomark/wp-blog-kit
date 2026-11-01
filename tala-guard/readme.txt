=== Tala Guard ===
Contributors: edventuredev
Tags: comments, spam, anti-spam, turnstile, captcha
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps spam bots out of comments with Cloudflare Turnstile, confirmed on the server before WordPress saves a comment. Works with any theme.

== Description ==

Spam bots post straight to WordPress's comment form, and every one of their comments lands in the moderation queue. Tala Guard puts Cloudflare Turnstile in the comment form: a check that tells people from bots, usually without asking people to do anything. A comment is saved only when Cloudflare confirms the check, so bots' comments never reach the queue.

* **Checked on the server.** The form's token is sent to Cloudflare's Siteverify before WordPress saves the comment, and the comment is accepted only when Cloudflare says the check succeeded, for the comment form, on this site's own address. A token works once and for five minutes, as Cloudflare designed it.
* **Fails closed.** When the check fails, or Cloudflare can't be reached, the comment isn't saved, and the visitor sees why, with a link back to their comment. A network error is retried once, so a brief hiccup doesn't turn anyone away.
* **Light on pages.** Turnstile's script loads when the comment form comes near the screen or is used, so readers who never comment don't download it, and the check has finished by the time a comment is written. Its place in the form is kept free, so nothing moves when it appears.
* **Any theme, any cache.** The widget goes right before the comment form's submit button, through WordPress's own comment form, which block themes and classic themes both use. It is plain markup with the public site key, so pages with comment forms can be cached for everyone.
* **Dark mode and phones.** The widget follows the visitor's light or dark mode and takes the form's width.
* **Only the public form.** Comments written in wp-admin and comments posted by logged-in users through the REST API don't go through the comment form, and aren't asked for a token.
* **Keys stay private.** The secret key is never shown again after it's saved, and it can live in wp-config.php instead of the database.

Developers can add hostnames a token may come from with the `tala_guard_hostnames` filter (the site's own addresses by default), and log turned-away comments with the `tala_guard_turned_away` action, which gets Cloudflare's answer.

== External services ==

Tala Guard uses Cloudflare Turnstile (https://www.cloudflare.com/application-services/products/turnstile/), Cloudflare's check that tells people from bots. It does nothing until both Turnstile keys are entered.

* **In the visitor's browser:** on pages with a comment form, Turnstile's script loads from challenges.cloudflare.com when the form comes near the screen or is used. It sends Cloudflare technical information about the browser and device, and the visitor's IP address, to tell a person from a bot. It doesn't read what is typed into the form.
* **From the server:** when a comment is sent through the comment form, the site sends Cloudflare's Siteverify (challenges.cloudflare.com/turnstile/v0/siteverify) the form's Turnstile token, the site's Turnstile secret key, the visitor's IP address and a random request ID. Nothing from the comment itself is sent.

Cloudflare's terms: https://www.cloudflare.com/website-terms/ — Turnstile's privacy addendum: https://www.cloudflare.com/turnstile-privacy-policy/ — Cloudflare's privacy policy: https://www.cloudflare.com/privacypolicy/

== Installation ==

1. Download the zip of the latest version from https://github.com/llegomark/wp-blog-kit/releases (under Assets), then upload it in Plugins → Add New Plugin → Upload Plugin, or run `wp plugin install tala-guard-<version>.zip --activate`.
2. In Cloudflare's dashboard, under Turnstile, add a widget for your site's domain. The Managed type is recommended. A free Cloudflare account is enough, and the site doesn't need to use Cloudflare otherwise.
3. Open Settings → Discussion, and under "Spam check for comments" enter the widget's site key and secret key.
4. Open a post with comments as a visitor (in a private window) and send a test comment.

To keep the keys out of the database, define them in wp-config.php instead: `define( 'TALA_GUARD_SITE_KEY', '0x…' );` and `define( 'TALA_GUARD_SECRET_KEY', '0x…' );`.

== Frequently Asked Questions ==

= What do visitors see? =

A small Cloudflare box above the comment form's button, which usually ticks itself within a second or two. Visitors Cloudflare is unsure about are asked to click it. Visitors without JavaScript are told to turn it on to comment.

= What happens to a comment from a bot? =

It isn't saved: the bot gets an error page instead of a place in your moderation queue.

= What if someone sends their comment before the check has finished? =

The form waits for the check, says so, and then sends the comment by itself.

= What if Cloudflare is down? =

Comments from the form are turned away while Cloudflare can't confirm the check (the visitor is asked to try again in a minute), so spam can't slip in during an outage. Everything else on the site keeps working.

= Does it work with page caching? =

Yes. The form carries only the public site key and Turnstile makes a new token in each visitor's browser, so a cached page works for everyone.

= Can I use the same widget for other forms? =

Yes. Tala Guard asks for the "comment" action and accepts only tokens for it, so a token from another form on the same widget can't be used to comment.

= Does it change my privacy policy? =

Turnstile sends Cloudflare technical information about the visitor's browser and device, and the IP address, to tell people from bots. Tala Guard suggests a paragraph for your policy under Settings → Privacy → Policy Guide.

= Can I try it on a test site? =

Yes, with Cloudflare's test keys (developers.cloudflare.com/turnstile/troubleshooting/testing/), which work on any address. With them every comment passes, or every comment fails, as the test key says, and Settings → Discussion warns that the check doesn't tell people from bots.

== Screenshots ==

1. The comment form on a phone, with Turnstile's check above the button.
2. Settings → Discussion: the Turnstile keys, with the secret key saved and not shown.

== Changelog ==

= 1.0.0 =
* First release: Cloudflare Turnstile in the comment form, confirmed with Cloudflare's Siteverify (success, the "comment" action and the site's hostname) before a comment is saved, failing closed; the script loads as the form comes near; keys on Settings → Discussion or in wp-config.php.

== Upgrade Notice ==

= 1.0.0 =
First release. After activating, enter your Turnstile site key and secret key on Settings → Discussion; until then the comment form works as before.
