=== WP e-Commerce Recommendations ===
Contributors: nToklo
Tags: wordpress, e-commerce, recommendations, retail
Requires at least: 3.5
Tested up to: 3.7.1
Stable tag: 1.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Get recommendations on your WordPress e-Commerce store quickly and easily, engage your customers and sell more.

== Description ==

WP e-Commerce Recommendations allows users of the WordPress e-Commerce platform to easily place recommendations and 
charts on their stores. The widgets have a range of layouts and colour schemes and all API integration is handled 
behind-the-scenes, making it quick and easy to set up.

### Features: ###

1. Recommendations are proven to increase sales and, until now, have not been affordable or easy for small business to make use of
2. nToklo's service is free for up to 100,000 events per day
3. Analytics console for your nToklo account shows user activity, best-performing recommendation location, purchase funnel and busiest times of day / week / month
4. Plugin installation is quick and easy - you can create your (required) nToklo account without leaving your Wordpress admin panel
5. Recommendations or charts can be displayed on your site from WP's admin with widgets - no technical knowledge required
6. Seven styles and colour schemes to choose from
7. With some technical knowledge, widgets can be included in any area of the site with a function call in your templates

== Installation ==

1. You can either install via the Plugins > Add new menu item in your WordPress admin or download recommendations-for-wp-ecommerce.zip from the WordPress plugin directory (if you’ve done the latter, you’ll need to upload this file to the /wp-content/plugins/ directory of your store and then unzip it.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Create an nToklo account as instructed on the "Settings > Store > Recommendations" tab.
4. Once you’ve set up your nToklo account, you must activate the account by clicking on the link that you receive in your verification email.
5. Once that’s done you need to let people use your site for at least 24 hours. Please note that we cannot show you recommendations or charts until we have some user activity posted to our servers.
6. Place widgets on your pages using either the widget menu, or by using function calls like: do_shortcode(["ntoklo_chart", "max_items=5&amp;widget_color=orange"]);

== Frequently Asked Questions ==

= Why do I need an account with nToklo? =

You need one because user activity data is sent from your site to our servers and stored there, so that we can process the data, understand your user behaviour, and send back recommendations when you request them. The computations have to be run on our servers and, with up to 100,000 events per day supported for free accounts, you really don't want to have all of that data taking up disk space on your server.

= How do I set up an nToklo account? =

From within the Wordpress admin page, as the first step after activating the plugin. Just go to /wp-admin/options-general.php?page=wpsc-settings&tab=recommendation_system. You'll see two buttons - one for new customers and one for customers who already have an nToklo account. Clicking one of these will display a panel with a registration form. Complete the form and you'll see a confirmation page with a code for you to copy from the panel into the textarea in your settings page, which is highlighted in a yellow panel.

= How do I put recommendations or a chart on my page? =

Assuming that you have already set up an account

You can place recommendations or charts on your store pages using the nToklo widgets, either on the widgets page by dragging a widget on to a sidebar, or by using shortcodes.

### 1) Via the widgets menu ###

This is the easiest way and is recommended for non-technical users. Go to the Appearance > Widgets page and drag either or both of the WPeC widgets on to your sidebar (WPeC chart or WPeC recommendations). Next you can configure settings for each widget and preview them on your store.

### 2) Using shortcodes ###

This method gives you greater flexibility when positioning your widget but is slightly more complicated

For recommendations, you should call:

	[ntoklo_recommendations $arguments]

Where $arguments can be any of the following:

<table cellpadding="10" cellspacing="0" class="nt_settings_table">
	<thead>
		<tr>
			<td>Key</td>
			<td>Accepted values</td>
			<td>Defaults</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>title</td>
			<td>String</td>
			<td>Recommended for you</td>
		</tr>
		<tr>
			<td>max_items</td>
			<td>An integer between 1 - 9. Please note that 2 and 3-column grids will only display multiples of 2 and 3 respectively.</td>
			<td>6</td>
		</tr>
		<tr>
			<td>layout</td>
			<td>
				<ul><li>row</li><li>column_image_above</li><li>column_image_right</li><li>grid_2_column</li><li>grid_3_column</li><li>chart</li></ul>
			</td>
			<td>
				row
			</td>
		</tr>
		<tr>
			<td>image_width</td>
			<td>integer: can be any number, but must be appropriate to your layout</td>
			<td>220</td>
		</tr>
		<tr>
			<td>image_height</td>
			<td>As above</td>
			<td>140</td>
		</tr>
		<tr>
			<td>widget_color</td>
			<td>
				<ul><li>plum</li><li>pink</li><li>orange</li><li>green</li><li>blue</li><li>dark_blue</li></ul>
			</td>
			<td>
				plum
			</td>
		</tr>
	</tbody>
</table>

These arguments should be passed as query string parameters, such as:

	layout=grid_2_column&image_width=190&image_height=100&widget_color=blue&max_items=4

Meaning that call to a recommendation widget might look like this:

	[ntoklo_recommendations layout=grid_2_column image_width=190 image_height=100 widget_color=blue max_items=4]

Charts are called in a similar way, but with different options:

	[ntoklo_chart $arguments]

$arguments for charts can be any of the following:

<table cellpadding="10" cellspacing="0" class="nt_settings_table">
	<thead>
		<tr>
			<td>Key</td>
			<td>Accepted values</td>
			<td>Defaults</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>title</td>
			<td>String</td>
			<td>Recommended for you</td>
		</tr>
		<tr>
			<td>max_items</td>
			<td>Integer between 1 and 100</td>
			<td>10</td>
		</tr>
		<tr>
			<td>tw</td>
			<td>
				<ul>
					<li>DAILY</li>
					<li>WEEKLY</li>
				</ul>
			</td>
			<td>DAILY</td>
		</tr>
		<tr>
			<td>image_width</td>
			<td>integer: can be any number, but must be appropriate to your layout</td>
			<td>100</td>
		</tr>
		<tr>
			<td>image_height</td>
			<td>As above</td>
			<td>100</td>
		</tr>
		<tr>
			<td>widget_color</td>
			<td>
				<ul><li>plum</li><li>pink</li><li>orange</li><li>green</li><li>blue</li><li>dark_blue</li></ul>
			</td>
			<td>
				plum
			</td>
		</tr>
	</tbody>
</table>

A call to a chart widget might look like this:

	[ntoklo_chart title="Top 10" max_items=10 image_width=150]

= How do I change the style of the widget? =

There are six layout options for the recommendations widget, and one for the chart widget, as well as 6 colour schemes available to both. You can also alter the size of the images to suit your site design.

= I've set up an account and placed widgets on my page, but I can't see recommendations or charts - why not? =

1. Have you waited at least 24 hours since installing the plugin? 
2. Has there been any user activity on your site?

If there is very little activity on your site, we won't know very much about either your catalogue or your user behaviour, so it's very difficult for us to make recommendations.

= My recommendations don't make sense - why not? =

If there is very little activity on your site, we won't know very much about your product catalogue or user behaviour, so we have to make recommendations based on what we do know.

= Yesterday I saw recommendations on my site but now there aren't any - why not? =

If there was no activity on your site yesterday, then we can't serve recommendations

= How do I see what's been happening on my site? =

The nToklo console at https://console.ntoklo.com/login will show you analytics or user activity on your site, including:

The nToklo console shows information about user activity on your store - think of it like Google Analytics, with a retail focus. You can:

1. See a snapshot of all activity on your site on the platform usage tab. How busy are you today / this week / this month?
2. See how well your recommendations are converting on the recommendations performance tab.
3. Find out what the best performing location for recommendations is and reposition them if necessary.
4. View your purchase funnel on the item activity tab, where user browsing history is broken down for you into browse, preview and purchase events.
5. See which times of the day, week and month are the busiest on the user activity tab.
6. See summary figures for today, this week and this month, in relation to the average, busiest and quietest days / weeks / months on on all four tabs.
7. Keep track of real-world events such as promotional campaigns, overlaying the data on the graphs using our annotations.

We've packed a ton of features into this console but still kept it easy to use, so why not take a look? Please note that you'll need an up-to-date browser, such as Chrome, Safari or Firefox (or IE10).

== Screenshots ==

1. To use our recommendations, you'll need an nToklo account. You can set this up from within your WordPress store's admin page. 
2. The nToklo account creation form.
3. Your nToklo account has now been created, and you'll see a code in the right-hand panel.
4. Copy the code into the left-hand panel to link your WordPress store with your nToklo account.
5. The recommendations settings page explains how to place charts and recommendations on your page, shows you what's selling on your store, and gives you a link to the nToklo console.
6. Recommendations showing on a page using the widgets - you get seven styles and siz colour schemes to choose from.
7. The nToklo console provides retail-focussed analytics giving you a complete picture of user activity on your store.

== Changelog ==

= 1.0 =
* Initial commit
