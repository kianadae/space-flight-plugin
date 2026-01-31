# Spaceflight News WordPress Plugin

A robust WordPress integration that fetches and synchronizes space exploration articles from the **Spaceflight News API (v4)**.

## 🚀 Key Features

* **Custom Post Type:** Articles are stored in a dedicated `Space News` post type (`sfn_news`) for clean content management.
* **Intelligent Sync:** Features an automated background sync via `wp_cron` with customizable frequencies (Hourly, Twice Daily, Daily).
* **Advanced Filtering:** Filter incoming news by specific keywords (e.g., "SpaceX", "NASA") and set a "Date Cutoff" to ignore legacy data.
* **Performance First:** * **Transients API:** Implements 1-hour caching for API responses to stay within rate limits.
    * **Smart Sideloading:** Automatically downloads remote images and attaches them as local **Featured Images** to ensure fast loading and SEO benefits.
    * **Deduplication:** Prevents duplicate content by mapping local posts to unique API IDs.
* **Admin Dashboard:** A custom settings page providing real-time stats, including total article counts and the countdown to the next auto-update.

## 🛠 Installation

1.  Upload the `spaceflight-news` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the **'Plugins'** menu in WordPress.
3.  The plugin will automatically initialize the Custom Post Type and schedule the first fetch.

## ⚙️ Configuration

Navigate to the **Spaceflight News** menu in your WordPress sidebar:

1.  **Search Phrase:** Enter a keyword to target specific news or leave blank for a general feed.
2.  **Date Cutoff:** Set a starting date for your news archive.
3.  **Update Frequency:** Choose how often the site should check for new content.
4.  **Manual Fetch:** Use the "Fetch News Now" button for an immediate synchronization.

## 🗄️ Technical Implementation Details

* **API Provider:** [Spaceflight News API](https://api.spaceflightnewsapi.net/v4/articles/)
* **Namespace:** `sfn_`
* **Hooks Used:**
    * `init`: Register CPT.
    * `admin_menu`: Settings page registration.
    * `wp_cron`: Automated background fetching.
    * `activation/deactivation`: Management of rewrite rules and cron schedules.

## 👤 Author

* **Christian Ada**
