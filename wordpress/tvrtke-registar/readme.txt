=== TVRTKE – Poslovni registar ===
Contributors: bren-wp
Tags: business directory, company search, registry, croatia
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.5.0
License: MIT
License URI: https://opensource.org/license/mit

Search Croatian court-registered companies through a separately hosted API, using a searchable public directory.

== Description ==

TVRTKE displays a search form and public profiles for Croatian companies based on
locally synchronized data from the Croatian Court Registry. Users can search by
business name, tax identifier (OIB), registration number (MBS), county, settlement,
registered activity, and status.

The plugin provides a Gutenberg block named "TVRTKE pretraga" and the shortcode
[tvrtke_pretraga]. The visible user interface is in Croatian and uses WordPress
translation functions to support localization.

**External service disclosure:** This plugin needs a separately hosted central
TVRTKE API and a database. It does not include the registry database itself.
The administrator configures an HTTPS service endpoint and must explicitly opt in
on the Settings > TVRTKE page before any external API query is made.

When an administrator has enabled the service and a visitor performs a search,
the WordPress server sends search terms (which can include company name, OIB,
MBS), selected filters, pagination and normal HTTP metadata to the configured
API server. The API host can see the requesting WordPress server's IP address
and normal network metadata. This plugin does not add visitors' IP addresses
as an explicit request parameter. Responses are cached using WordPress transients
for up to five minutes. No third-party scripts, analytics, advertising trackers
or cookies are added by the plugin.

Service operator: TVRTKE, https://tvrtke.com.hr/
Open-source central backend: https://github.com/bren-wp/Tvrtke

**Before WordPress.org submission:** the service operator must publish public
Terms of Service and a Privacy Policy covering API requests, retention and
possible server logs, and add verified links to both documents here. These pages
have not yet been verified for this prerelease.

The source data may be incomplete, especially when the shared-hosting importer
has a row limit. This plugin is not an official government service.

== Installation ==

1. Set up the separate PHP and MySQL central API according to the GitHub documentation.
2. Install the TVRTKE plugin ZIP in WordPress.
3. Open Settings > TVRTKE, provide a trusted HTTPS API URL and explicitly enable the service.
4. Add the "TVRTKE pretraga" Gutenberg block or the [tvrtke_pretraga] shortcode to a page.
5. Ensure an initial Court Registry synchronization has been published before searching.

== Frequently Asked Questions ==

= Is the business registry database included? =
No. A separately hosted PHP/MySQL API is required.

= Do visitors need an account? =
No. The site administrator configures access to the external API.

= Does the plugin display every Croatian company? =
Not necessarily. Shared hosting imports may be partial, in which case the
public search interface shows a partial-data warning.

= What is removed when the plugin is uninstalled? =
The plugin's local options are removed. Temporary cached API responses expire
according to the WordPress transient timeout.

== Changelog ==

= 0.5.0 =
* Added explicit administrator consent for external API connectivity.
* Added a dynamic Gutenberg block and a settlement filter.
* Added privacy-policy guidance and improved responsive interface styling.
* Removed plugin settings on uninstall.

= 0.4.0 =
* Improved the display of incomplete synchronization status.
