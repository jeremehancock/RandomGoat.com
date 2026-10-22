# 🐐 Random Goat

**Making the world a little more goat-y, one GIF at a time!**

[**🌐 Visit RandomGoat.com**](https://randomgoat.com)

## What is Random Goat?

Random Goat is a delightfully pointless website that does exactly what it says on the tin: **shows you random goat GIFs!**

Sometimes you just need to see a goat doing goat things. Whether they're jumping, being silly, wearing tiny sweaters, or just being adorably chaotic, these goats are here to brighten your day.

## ✨ Features

- **🎲 Seemingly endless Random Goats** - New goat GIF every 20 seconds, or click/tap for instant goat satisfaction
- **📱 Mobile-Friendly** - Goats look great on any device
- **🎃 Seasonal Fun** - Special holiday mascots appear during Halloween and Christmas
- **⚡ Lightning Fast** - Smart preloading ensures smooth goat transitions
- **🔗 Embeddable** - Share the goat joy on your own website
- **🎨 Beautiful Animations** - Smooth transitions and ripple effects for maximum goat enjoyment

## 🎯 The Mission

Life is better with goats. This website exists for one simple reason: **to make people smile**.

No sign-ups, no ads, no tracking, no monetization schemes. Just pure, unadulterated goat content delivered straight to your eyeballs.

## 🚀 How to Use

1. Go to [randomgoat.com](https://randomgoat.com)
2. Watch goat
3. Smile
4. Click/tap anywhere for another goat
5. Repeat until happiness achieved

**Pro tip:** Leave it open in a browser tab for automatic goat delivery throughout your day!

## 🛠 Technical Stuff

- **Pure HTML/CSS/JavaScript** - No frameworks, just good old-fashioned web tech
- **Responsive Design** - Works on phones, tablets, desktops, smart fridges (probably)
- **Intelligent Preloading** - GIFs are preloaded for instant goat gratification
- **Performance Optimized** - Adapts to your network speed because goats shouldn't buffer
- **Accessibility Friendly** - Keyboard navigation and screen reader support

## 🎭 Special Features

### Seasonal Mascots

Keep an eye out for special goat mascots during holidays:

- 🎅 **December**: Santa Goat spreads holiday cheer
- 👻 **October**: Spooky Halloween Goat gets in the spirit

### Embed on Your Site

Want goats on your website? Use the embed feature to add a goat widget anywhere!

### URL Parameters

- `/GIF id` or `/SHORTID` - Show a specific goat GIF
- `?season=christmas` - Force Christmas theme
- `?season=halloween` - Force Halloween theme

## 🚢 Deployment

randomgoat.com runs on Coolify from the `Dockerfile` in this repository, on the official `php:8.5-cli` image with
PHP's built-in web server. `router.php` keeps the repository's internal files off the site.

Coolify settings for the app:

- **Build Pack:** Dockerfile
- **Ports Exposes:** 8080
- **Persistent storage:** `/app/data` (goats.json) and `/app/goats` (the GIFs)
- **Environment variables:** `ADMIN_USERNAME` and `ADMIN_PASSWORD` for `/admin/`

The admin login refuses all logins for up to 15 minutes after 10 failed attempts, counted across everyone. If that
locks you out during an attack, wait it out or redeploy in Coolify, which starts a fresh container and clears the count.

PHP runs as `www-data`. On each start, `docker/start.sh` makes `www-data` the owner of anything in the two volumes it
does not already own, so the admin page can write to them.

### Keeping PHP up to date

`php:8.5-cli` is a moving tag, so each fresh build picks up the newest PHP 8.5 patch release. A running container
never updates itself: **redeploy in Coolify to pick up a new PHP release.**

The **PHP currency** workflow (`.github/workflows/php-currency.yml`) checks every Monday that the live site runs the
newest 8.5 release and fails, which sends an email, when it does not. It can also be run by hand from the Actions tab.
GitHub turns scheduled workflows off after 60 days without activity in the repository and emails a warning first;
re-enable it from the Actions tab when that happens.

## 📜 License

[MIT License](LICENSE)

## AI Disclosure

This site was originally developed without AI, but has since been rewritten using the assistance of AI.

## 🐐 Fun Facts

- Goats have rectangular pupils
- A group of goats is called a "trip" or "tribe"
- Goats were one of the first domesticated animals
- This website has probably made at least one person's day better

---

**Remember**: In a world full of chaos, be someone's random goat. 🐐
