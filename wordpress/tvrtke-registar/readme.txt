=== TVRTKE – Poslovni registar ===
Contributors: brendigo
Tags: business directory, company search, registry, croatia
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.8.0
License: MIT
License URI: https://opensource.org/license/mit

A modern Croatian business directory for companies, crafts, associations and foundations, powered by connected public registries.

== Description ==

**Find organizations. Explore public information. All in one place.**

TVRTKE brings a beautifully organized Croatian business search to WordPress.
Let visitors discover organizations by name or registration number, explore clear
profiles, and filter by registry, region, activity and status.

Key features:

* Search companies in the Croatian Court Registry and other connected public datasets.
* Support for crafts, associations, foundations and additional approved sources.
* Dedicated registry/category filter with source-aware search results.
* Lightweight, responsive directory cards and detailed public profiles.
* Dynamic Gutenberg search block and the [tvrtke_pretraga] shortcode.
* Local WordPress caching and no third-party JavaScript or advertising trackers.

**External service disclosure:** This plugin requires a separately deployed
TVRTKE API and a database. It does not include a complete Croatian registry.
The administrator configures a trusted HTTPS endpoint and must explicitly opt in
in the TVRTKE administration panel before the plugin makes external requests.

When a visitor searches after the service is enabled, the WordPress server
forwards the search term (which may include a company name, OIB or registration
identifier), selected filters, and pagination to the configured TVRTKE API.
The receiving API can see ordinary HTTP metadata such as the WordPress server IP.
This plugin does not deliberately transmit visitors' IP addresses as query
parameters, does not set tracking cookies, and does not include external scripts.
Responses are cached in WordPress transients for up to five minutes.

Service operator and information: https://tvrtke.com.hr/
Open-source central backend: https://github.com/bren-wp/Tvrtke
The service operator must publish an accessible privacy policy and terms of use
for the configured API before submitting the plugin to WordPress.org. Verified
policy URLs have not yet been supplied for this release.

Source data coverage varies by registry, import status, licensing and hosting
capacity. An absent registry is not silently presented as a complete dataset.
TVRTKE is independent software, not an official Croatian government service.

== Installation ==

1. Configure a separately hosted TVRTKE PHP/MySQL API and complete the applicable database migrations.
2. Upload the plugin ZIP in WordPress and activate it.
3. In TVRTKE > API postavke, enter the trusted API HTTPS URL and explicitly enable the external service.
4. Add the Gutenberg block "TVRTKE pretraga" or the shortcode [tvrtke_pretraga].
5. Once official source data has been imported, visitors can search the published records.

== Frequently Asked Questions ==

= Does the plugin already contain every Croatian business and association? =
No. Data is populated from distinct authorized registries. Availability,
completeness and freshness depend on successfully imported source datasets.

= Are visitors required to register? =
No. Visitors search publicly available records. The administrator controls
external service connectivity.

= Where does the search data go? =
To the HTTPS API endpoint configured by the site administrator, as described above.

= What happens when I uninstall the plugin? =
The plugin removes its own configuration options. WordPress cached data
expires through standard transient expiration.

== Changelog ==

= 0.8.0 =
* New reference-matched Croatian TVRTKE business directory interface with responsive five-field search bar.
* Native, keyboard-accessible "Više filtara" advanced search and resilient mobile layouts.
* Rebuilt admin dashboard and layout settings with adaptive design for narrow WordPress screens.
* Clearer category links, individual registry profiles and publicly sourced status information.
* Updated plugin branding and release metadata: Author Brendigo, contributor brendigo.


= 0.7.0 =
* Official registry lifecycle information, including status, start date and removal date when publicly supplied.
* Multi-registry results enriched with registration provenance and source freshness.
* More robust WordPress read-only input validation and administrator opt-out.
* Compatible with a central backend that privately synchronizes official feeds; no API credentials bundled.
* Displays original registration and status-change dates separately where available.

= 0.6.0 =
* Multi-registry category search for companies, crafts, associations and foundations.
* Source-aware profiles and registration identifiers.
* Updated accessibility, responsive user interface, and public service disclosures.

= 0.5.0 =
* Added Gutenberg block, consent gate, settlement filter and privacy-policy guidance.
