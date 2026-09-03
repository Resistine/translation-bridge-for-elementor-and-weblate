=== Translation Bridge for Elementor and Weblate ===
Tags: elementor, weblate, translation, polylang, localization
Requires at least: 6.4
Stable tag: 0.1.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export Elementor page text to Weblate as gettext POT and import translated PO files into Polylang Elementor pages.

== Description ==

Translation Bridge for Elementor and Weblate connects Elementor page content with a standard gettext/Weblate translation workflow without requiring WPML or a translatable WordPress theme.

The plugin reads human-facing strings from Elementor documents, exports them as a gettext POT template, and stores a stable source pointer in each entry's `msgctxt`. After translation in Weblate, a translated PO file can be imported into the corresponding Polylang pages.

= Main features =

* Select a Polylang source language during export; the default language is preselected.
* Export Elementor headings, text, buttons, form labels, excerpts and other likely human-facing strings.
* WordPress post/page titles are optional and disabled by default, avoiding accidental renaming of administrative translated pages such as `Home - Polski`.
* Keep repeated source strings independent by using gettext context.
* Include page, widget and setting information for translators.
* Mark HTML content and tell translators to preserve tags and attributes.
* Validate HTML tag/attribute sequences during import; translations with changed markup are skipped.
* Normalize CR, Unicode line separator (U+2028) and paragraph separator (U+2029) characters to PO-safe `\n` line breaks.
* Filter Elementor editor/internal metadata, technical controls, CSS/HTML layout values, symbol-only strings and known template noise.
* Resolve translated pages with Polylang.
* Optionally create missing Polylang translations as drafts by cloning the source Elementor layout.
* Preserve existing translated layouts by default, with optional source-layout re-sync.
* A local **View details** link opens the normal WordPress plugin-information modal from this bundled `readme.txt`.

= What is filtered from Weblate? =

The exporter deliberately omits values that are not useful translation units:

* Elementor underscore-prefixed editor/internal settings such as `_title`.
* HTML/CSS/layout enum values such as `h1`, `center`, `space-between`, `vertical-padding`, `flex-start` and similar controls.
* Pure markup with no visible text.
* Punctuation/symbol-only values such as `/` and `€`.
* The known Divider/template label `Trenner`.
* `card`, `HTML` and `item` when they occur as structural/non-content values. They are not globally blacklisted because the same words can be genuine visible content.

Visible terminology is intentionally **not** blacklisted merely because a Polish translation happens to be identical. Examples include `EDR`, `XDR`, `Endpoint Detection and Response`, `Enterprise`, `Dashboard`, `Threat Intelligence`, `VADs` and product/brand names.

= HTML and Weblate =

HTML-containing Elementor text remains one translation unit. For example:

`<p><span style="font-weight: 400;">Simple text.</span></p>`

The translator should translate only `Simple text.` and keep the tag sequence and attributes unchanged. The exporter adds an HTML translator comment and the importer checks the tag/attribute sequence before writing a translation to Elementor.

= Typical workflow =

1. Edit source-language pages in Elementor.
2. Open **Tools > Elementor → Weblate**.
3. Select the source language and export `messages.pot`.
4. Commit/update the POT file in the repository connected to Weblate.
5. Translate in Weblate.
6. Download or pull the target-language `.po` file.
7. Select the target Polylang language and import the PO file.
8. Review newly created draft translations if any.
9. Publish and run Simply Static as needed.

= Suggested Weblate component settings =

* File mask: `po/*.po`
* Template for new translations: `po/messages.pot`
* File format: Gettext PO file

= Requirements =

**Elementor is required.**

**Polylang is optional for export** and required for automatic PO import mapping and creation of translated pages.

**Weblate is external and not bundled.** Standard gettext POT/PO files are used.

No Elementor, Polylang, Weblate, WordPress or Simply Static source code is bundled in this plugin.

= Extending the filter =

Developers can override the extraction decision with `ewb_is_translatable_setting`:

`add_filter( 'ewb_is_translatable_setting', function( $decision, $path, $value, $widget ) {
    if ( end( $path ) === 'my_custom_widget_copy' ) {
        return true;
    }
    return $decision;
}, 10, 4 );`

Return `true` to force inclusion, `false` to force exclusion, or `null` to keep the built-in decision.

= Import safety =

* Empty PO translations are ignored and never erase source content.
* Existing target layouts are preserved unless layout synchronization is explicitly enabled.
* Entries whose Elementor element ID or setting path no longer exists are skipped.
* HTML entries with modified tags or attributes are skipped.
* Automatically created translations are drafts.
* Post/page titles are not exported by default.

Test bulk imports on a staging copy and maintain normal WordPress backups.

== Installation ==

1. Install and activate Elementor.
2. Upload this plugin ZIP through **Plugins > Add New > Upload Plugin**, or copy the folder to `/wp-content/plugins/`.
3. Activate **Translation Bridge for Elementor and Weblate**.
4. For import support, install/configure Polylang and create the target languages.
5. Open **Tools > Elementor → Weblate**.
6. Export `messages.pot`.

== Frequently Asked Questions ==

= Does this require WPML? =

No. The plugin provides a file-based Weblate workflow without WPML.

= Does this require Polylang? =

Not for export. Polylang is required for the built-in import mapping and translated-page creation.

= Why are `/`, `€` and `Trenner` no longer exported? =

They do not require linguistic translation. `/` and `€` are symbol-only values, while `Trenner` was identified in real Elementor exports as Divider/template noise.

= Why are EDR, XDR or Dashboard still exported? =

They are visible terminology, even when the target language intentionally keeps the English term. Keeping them in Weblate lets translators make that decision per language and preserves terminology control.

= What happens to HTML? =

HTML text remains translatable. Tags and attributes must remain unchanged. The importer verifies this before applying the translation.

= Why are post/page titles disabled by default? =

A translated page can have an administrative title such as `Home - Polski`. Excluding WordPress titles prevents an import from unexpectedly renaming it. Enable title export when you explicitly want WordPress titles translated.

= Can I use another translation system instead of Weblate? =

Yes. Any system that supports gettext POT/PO and preserves `msgctxt` can potentially be used.

== Changelog ==

= 0.1.6 =
* Reviewed a real English-to-Polish translation round trip and separated technical noise from visible untranslated terminology.
* Excluded punctuation/symbol-only values such as `/` and `€`.
* Excluded the known Divider/template value `Trenner`.
* Added context-sensitive handling of `card`, `HTML` and `item` instead of globally blacklisting ordinary words.
* Kept visible terms such as EDR, XDR, Dashboard and Threat Intelligence in translation catalogs.
* Added optional WordPress post/page-title export, disabled by default.
* Normalized CR, U+2028 and U+2029 to PO-safe escaped line breaks.
* Improved technical-setting path detection so human-facing fields such as `link_text` and `image_alt` are not excluded just because their names contain technical words.
* Added explicit HTML translator guidance to POT entries.
* Added strict HTML tag/attribute sequence validation during PO import.

= 2026.9.1 =
* Added an Export language selector when Polylang is active.
* Preselects the Polylang default language while still allowing All languages.
* Filters exported Elementor documents to the selected language.
* Keeps the stable `messages.pot` filename and adds an `X-Source-Language` header.

= 2026.8.4 =
* Excluded underscore-prefixed Elementor editor/internal settings such as `_title`.

= 2026.8.3 =
* Tightened technical HTML/CSS/Elementor filtering.
* Added the local **View details** modal sourced from `readme.txt`.

= 2026.8.2 =
* Filtered markup-only and common HTML/CSS enum values.

= 2026.8.1 =
* Added GPL-2.0-or-later licensing and WordPress.org-style package metadata.

= 2026.8.0 =
* Initial experimental release.

== Upgrade Notice ==

= 2026.9.1 =
Recommended for Weblate workflows: cleaner translation catalogs, PO-safe line breaks, safer HTML imports and optional page-title export.

= 2026.9.4 =
Just some WordPress bureaucracy.

=== Translation Bridge for Elementor & Weblate ===
Contributors: resistine
Tags: elementor, weblate, translation, polylang
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
