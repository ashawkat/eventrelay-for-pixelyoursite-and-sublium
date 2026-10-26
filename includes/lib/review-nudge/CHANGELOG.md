# Changelog

## 1.0.1

- Fixed: the "Rate ★" plugin action link double-escaped its star character through `esc_html()`, displaying the raw `&#9733;` entity. Uses a literal UTF-8 star now.

## 1.0.0

- Initial release: single-file WordPress.org review prompt — dismissable admin notice, quiet footer prompt on the host plugin's screens, and a "Rate ★" plugin action link.
- Triggers: `delay_days` after install, or immediately on the host plugin's `success_hook`.
- "Maybe later" reschedules by `later_days`; after `max_reminders` snoozes, or on "No thanks", the plugin stops asking for good.
- Slug-namespaced state (option key `{slug}_review_nudge`), nonce, and AJAX action, so several plugins can vendor the library on one site without colliding.
- All strings translate under the host plugin's text domain via the `textdomain` config.
