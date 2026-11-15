=== Sidcraft Restaurant for Sidcraft Commerce ===
Contributors: ssiddiqi4
Donate link: https://paypal.me/SSiddiqi275
Tags: restaurant, menu, food, qr code, online ordering
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restaurant menus for Sidcraft Commerce: unlimited menus and items, six responsive styles, filters, menu schema, QR codes and online ordering.

== Description ==

Sidcraft Restaurant turns your [Sidcraft Commerce](https://wordpress.org/plugins/sidcraft-commerce-for-sidcraft-page-builder) for [Sidcraft Page Builder](https://wordpress.org/plugins/sidcraft-page-builder) site into a restaurant website. Build as many menus as you like (Breakfast, Lunch, Dinner, Wine, Drinks, Café, Kids…), split them into sections (Appetizers, Main Course, Dessert, Gluten Free, Weekly Specials…) and fill them with dishes and drinks. Change the menu any time — no reprinting, and guests always see what is on today.

https://www.youtube.com/watch?v=cTvCMLRojPQ

**Menus, sections and items**

* Unlimited menus, sections and menu items. A section can be used in several menus.
* Each section can have a note, for example “All items in this section come with salad or fries.”
* Each menu can have a footer for allergy notes, service charges or legal disclaimers.
* Photos and one or several prices per item (Small / Large, Glass / Bottle, Cup / Bowl).
* Organise everything in the menu manager: drag sections and the items inside them into order, add existing sections and items, or type new sections.
* Item categories, used by the category filter.

**Six responsive styles**

* Classic (name … price), Photo grid, Photo list, Compact (two columns), Luxe (a minimal, elegant layout for wine lists and fine dining) and Ordering (add dishes to the cart right from the menu).
* Every style adapts to phones, tablets and wide screens, and to the space it is placed in.
* Optional sections sidebar for quick navigation; on phones it becomes a scrolling bar that follows you down the menu.

**Find the right dish**

* Search box, category filter, dietary filter and sorting by name or price. Filtering happens in the browser, so menu pages stay fast and cacheable.
* Dietary and ethical icons: vegetarian, vegan, gluten-free, dairy-free, nut-free, egg-free, organic, halal, kosher, spicy, low carb, sugar-free, locally sourced, fair trade and sustainably sourced — with a dietary key under the menu.
* Badges: New, Special, Chef’s pick, Popular, Seasonal, plus a Sale badge while a promotion runs.

**Prices and promotions**

* Promotions beyond a simple sale price: a percentage or an amount off, between two dates, on chosen weekdays and between daily times (happy hour, lunch deals, weekend specials). The menu shows the old and new price automatically while it runs.

**Item pages**

* Each item can have its own page with its description, prices, icons, details, reviews and related items.
* Custom fields for any extra information (calories, allergens, wine region, pairing), shown on the item page and, if you like, on the menu.
* Suppliers with a map, to show off local farms or ethical sourcing.
* Related items, chosen by you or suggested from the same category.

**Online ordering with Sidcraft Commerce**

* Tick “Customers can order this online” on an item and it can be added to the cart from the Ordering style and its own page. Several prices become a choice (for example 10" or 14"). Cart, checkout, payment, emails and orders come from Sidcraft Commerce, including item options and order statuses.
* Promotions apply to online orders too.
* Reviews for orderable items use Sidcraft Commerce’s verified-customer reviews.

**Add menus anywhere**

* [Sidcraft Page Builder](https://wordpress.org/plugins/sidcraft-page-builder) Units: Restaurant Menu, Menu Section, Menu Item, Menu Items List and Menu QR Code. From a menu’s screen, “Design a page in Sidcraft Page Builder” creates a page with the menu and opens it in the builder.
* Gutenberg blocks for the same five elements, in a Restaurant category.
* Block patterns (menu page, photo grid, drinks list, order online, weekly specials, chef’s special, specials list, table QR card) for pages and block theme templates.
* Shortcodes: `[sidrest_menu menu="12"]`, `[sidrest_section section="34"]`, `[sidrest_item item="56"]`, `[sidrest_items source="badge" badge="special"]`, `[sidrest_qr menu="12"]`.
* A widget to list menu items in sidebars and footers.
* Every menu also has its own page (yoursite.com/restaurant-menu/dinner/).

**QR codes**

* Make a QR code that opens your menu, download it as PNG or SVG, or print table cards. Put them on your tables for a contactless menu that saves printing costs and paper. The Menu QR Code block and Unit place one on any page.

**Search engines**

* schema.org Menu structured data (Menu, MenuSection, MenuItem with prices and dietary information) is added automatically, so search engines can recognise your page as a menu. Add your restaurant’s name and cuisine in the settings to describe it as a Restaurant.

**Make it yours**

* Styling settings: colours, fonts, borders, corner radius, spacing, widths and photo shape.
* Labelling settings: change every piece of wording shown on the front end.
* Templates you can override in your theme (copy files from `templates/` into `yourtheme/sidcraft-restaurant/`).
* Works with WPML and Polylang for multilingual menus (wpml-config.xml included).

**How an order Payment Gateway works**

* When you tick “Customers can order this online”, the plugin creates a matching [Sidcraft Commerce](https://wordpress.org/plugins/sidcraft-commerce-for-sidcraft-page-builder) product and keeps it in sync with the dish.
* Several prices on a dish become a choice of size or option for the customer.
* Any promotion that’s running sets the price.
* From the cart onward, [Sidcraft Commerce](https://wordpress.org/plugins/sidcraft-commerce-for-sidcraft-page-builder) handles everything: checkout, the payment gateway, emails and order statuses.
* So whatever gateways you’ve set up in [Sidcraft Commerce](https://wordpress.org/plugins/sidcraft-commerce-for-sidcraft-page-builder) work for restaurant orders automatically. A restaurant that only wants to show its menu doesn’t need to set up payments at all.

== External services ==

**Google Maps** — when an item has a supplier with an address and “Maps for suppliers” is on (Restaurant → Settings → General), the item’s page embeds a Google Map of that address. The visitor’s browser then loads the map from maps.google.com, sending the address as the search and the usual browser information (such as the IP address). Nothing is sent when no supplier addresses are entered or the setting is off. Terms: https://policies.google.com/terms — Privacy: https://policies.google.com/privacy

**DoorDash Drive** — only when DoorDash is offered at your checkout with the courier set to “Automatic” and API keys are entered (Restaurant → Settings → Delivery). When a delivery order is paid, your site sends DoorDash (openapi.doordash.com) the restaurant’s name, phone, address and pickup instructions, and the customer’s name, phone, delivery address, order notes and order value, to book a courier. Terms: https://www.doordash.com/terms/ — Privacy: https://www.doordash.com/privacy/

**Uber Direct** — only when Uber Eats or Postmates is offered at your checkout with the courier set to “Automatic” and API credentials are entered. Your site gets an access token from auth.uber.com and sends api.uber.com the same pickup and delivery details plus the item names, quantities and prices, to quote and book a courier. Terms: https://www.uber.com/legal/ — Privacy: https://www.uber.com/legal/privacy/

**Your delivery webhook** — only when a platform’s courier is set to “webhook” and you enter a URL. Paid delivery orders (items, totals, customer contact and address, pickup details) are posted to the URL you chose; that service’s own terms apply.

**Courier status updates** — DoorDash, Uber or your order hub can post status updates to your site’s status URL (shown under Restaurant → Settings → Delivery). Nothing is sent from your site for this.

**“Order on …” links** — plain links to the store pages you enter. Nothing is sent to the platforms until a visitor clicks one.

QR codes are drawn in the browser with the bundled qrcode-generator library; no external service is used.

== Installation ==

1. Install and activate [Sidcraft Page Builder](https://wordpress.org/plugins/sidcraft-page-builder) and [Sidcraft Commerce](https://wordpress.org/plugins/sidcraft-commerce-for-sidcraft-page-builder) (0.9 or newer).
2. Install and activate Sidcraft Restaurant.
3. Go to Restaurant → Menu items and add your dishes and drinks.
4. Go to Restaurant → Menus, add a menu, and in “Sections and items” add sections and their items.
5. Show the menu with “Design a page in Sidcraft Page Builder”, a block, a pattern, a shortcode, or the menu’s own page.

== Frequently Asked Questions ==

= Do I need Sidcraft Commerce if I don’t sell online? =

Yes, Sidcraft Restaurant is an add-on for Sidcraft Commerce (and Sidcraft Page Builder). You don’t have to set up payments if you only want to show your menu.

= Can one item have several prices? =

Yes. Add as many price rows as you need and give each a name, for example Glass and Bottle.

= How do promotions work? =

On an item, open Promotion, choose a percentage or an amount off, and optionally dates, weekdays and daily times. While it runs, the menu shows the old and the new price and a badge, and online orders use the new price.

= Can I show just one section or one dish on a page? =

Yes. Use the Menu Section or Menu Item Unit or block, or the `[sidrest_section]` and `[sidrest_item]` shortcodes.

= How do I make a QR code for my tables? =

Go to Restaurant → QR codes, pick the menu, and download the code or print table cards.

= Does it work with page caching? =

Yes. Search, filters and sorting run in the browser on the cached page. Promotions limited to certain hours change the displayed price when the page is next built, so keep cache lifetimes short if you use hourly promotions.

= How do I remove all data when deleting the plugin? =

Settings are removed when you delete the plugin. To remove menus, sections, items and categories too, add `define( 'SIDREST_REMOVE_ALL_DATA', true );` to wp-config.php before deleting it.

== Screenshots ==

1. Classic menu with section sidebar, search, category and dietary filters.
2. Photo grid style.
3. Ordering style with size choices and the cart.
4. Luxe style for a wine list.
5. A menu item page with suppliers, reviews and related items.

== Changelog ==

= 1.1.3 =
* Performance: a menu loads its sections, all their items (with meta and categories) and the products of orderable items in a few batched queries. A 200-item menu went from 746 database queries to 49 and renders in about a third of the time. The Menu Items List unit and widget (latest, category, section, badge and promo sources) batch their items the same way.
* Performance: labels, badge names and dietary icons are translated once per request instead of once per item, and templates look for a theme override once per request.
* Performance: block registration builds attributes without loading the menu, section, item and category pickers, and the block editor's settings are built only in the block editor. Pages without a menu no longer read those lists on every request.
* Performance: the rewrite-flush flag and the settings option are stored autoloaded, so they no longer cost a database query on every page.

= 1.1.2 =
* Performance: menu categories for the Menu Items List unit and widget are loaded once per request and shared with Sidcraft Builder Pro, so editor screens no longer query them twice.

= 1.1.1 =
* Fixed: "Translation loading triggered too early" notice (WordPress 6.7+). The Out for delivery order status label is now translated on init.

= 0.1.1 =
* Fixed errors and warnings reported by Wordpress pluhin check.

= 0.1.0 =
* First release: menus, sections and items; six styles; search, filters and sorting; sections sidebar; dietary icons and badges; multiple prices; promotions; custom fields; suppliers with maps; related items; online ordering through Sidcraft Commerce; Sidcraft Page Builder Units, blocks, patterns, shortcodes and a widget; QR codes; menu structured data; styling and labelling settings; WPML and Polylang support.

== Credits ==

QR codes use qrcode-generator by Kazuhiko Arase (MIT licence), https://github.com/kazuhikoarase/qrcode-generator — bundled in assets/js/vendor/qrcode.js.
