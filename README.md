# LiteSpeed Cron Intervals

A lightweight companion plugin for [LiteSpeed Cache for WordPress](https://wordpress.org/plugins/litespeed-cache/) (LSCWP) that lets you set custom run intervals for LSCWP cron tasks.

By default, LSCWP runs most of its optimization tasks every 15 minutes. If you change them with other tools (such as WP Crontrol), LSCWP resets them to 15 minutes. This plugin keeps your chosen intervals in place.

## ✨ Features

* **Per-task intervals:** Choose a preset (1 min to 24 hours) or a custom value in seconds for each task.
* **Keeps your settings:** LSCWP can't change your intervals back to its default.
* **Live status:** See each task's current schedule and next run time.
* **Built-in limits:** Image Optimization Auto Request can't be set below 5 minutes, which is QUIC.cloud's rate limit.
* **Clean removal:** Deactivating the plugin puts tasks back on LSCWP's default schedule. Uninstalling it also deletes its saved settings.

## 🧩 Supported Tasks

| Task | Hook |
|---|---|
| Unique CSS (UCSS) | `litespeed_task_ucss` |
| Critical CSS (CCSS) | `litespeed_task_ccss` |
| Viewport Images (VPI) | `litespeed_task_vpi` |
| LQIP Placeholder | `litespeed_task_lqip` |
| OptimaX | `litespeed_task_optimax` |
| Gravatar Cache | `litespeed_task_avatar` |
| Image Optm – Auto Request | `litespeed_task_imgoptm_req` |
| Image Optm – Pull | `litespeed_task_imgoptm_pull` |
| Crawler | `litespeed_task_crawler` |
| Guest Mode IP/UA Sync | `litespeed_task_guest_sync` |

## 🚀 Installation

1. Download the latest release `.zip` file from this repository.
2. In your WordPress admin, go to **Plugins** > **Add New** > **Upload Plugin**.
3. Select the `.zip` file, click **Install Now**, then **Activate Plugin**.

## 💻 Usage

1. Go to **Settings** > **LiteSpeed Cron**.
2. Pick an interval for each task, or leave it on **LSCWP default**.
3. Click **Save Intervals**.

A task shown as **not scheduled** is either turned off in LSCWP or waiting on a QUIC.cloud `try_later` timeout. Your interval is applied as soon as LSCWP schedules the task again.

## ⏱️ Reliable Short Intervals

WP-Cron only runs when someone visits your site. For accurate 1–5 minute intervals, use a real server cron instead:

1. Add this to `wp-config.php`:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
2. Add a server cron job that runs every minute (replace `example.com` with your domain):
   ```
   * * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

> **Note:** UCSS and CCSS each process one queue item per run, and QUIC.cloud may slow requests down (`try_later`).

## ⚠️ Requirements

* **WordPress:** 5.0 or higher
* **PHP:** 7.0 or higher
* **Required Plugin:** [LiteSpeed Cache for WordPress](https://wordpress.org/plugins/litespeed-cache/)

## 🛡️ Disclaimer

Short cron intervals make your server work more often. Each run uses CPU, memory and PHP workers.

* **Shared or low-resource hosting:** Short intervals (1–5 minutes) are **not recommended**. Keep the LSCWP default or use longer intervals so your site doesn't slow down or hit your host's resource limits.
* **VPS or dedicated servers:** If you have enough resources, you can use shorter intervals. Start with a moderate value and lower it step by step while checking server load.

Pick intervals that match your server. You are responsible for any performance impact caused by the intervals you set.

## 📝 License

This project is licensed under the GPLv2 or later License - see the `LICENSE` file for details.
