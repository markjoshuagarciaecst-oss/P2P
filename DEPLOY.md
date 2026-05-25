# Deploying Time for Skill

## Recommended Free Hosts (PHP + MySQL)

| Host | Free? | Notes |
|------|-------|-------|
| **InfinityFree** | ✅ Free forever | Best free option, no ads |
| **000webhost** | ✅ Free | Easy file manager |
| **Render** | ✅ Free tier | Sleeps after 15 min inactivity |
| **Railway** | $5 credit/mo | Most reliable |

---

## Step-by-Step: InfinityFree (Recommended Free)

### 1. Create account
- Go to https://infinityfree.com → Sign up free
- Create a new hosting account → choose a free subdomain (e.g. `yoursite.infinityfreeapp.com`)

### 2. Create the database
- In your InfinityFree control panel → **MySQL Databases**
- Create a new database — note the:
  - Database name (e.g. `epiz_12345678_skillswap`)
  - Database user (e.g. `epiz_12345678_skillswap`)
  - Password (you set this)
  - Host (shown in panel, e.g. `sql200.infinityfree.com`)

### 3. Import the database
- In control panel → **phpMyAdmin**
- Select your database → **Import** tab
- Upload `database.sql` from this project
- Then run the migration SQL manually (copy from `migrate.php` — the ALTER TABLE lines)

### 4. Update config/database.php
Edit these lines with your host's values:
```php
define('DB_HOST', 'sql200.infinityfree.com');   // from your panel
define('DB_NAME', 'epiz_12345678_skillswap');    // your DB name
define('DB_USER', 'epiz_12345678_skillswap');    // your DB user
define('DB_PASS', 'your_db_password');           // your DB password
```

### 5. Update APP_URL
In `config/database.php`, the URL auto-detects. But to be safe, set it explicitly:
```php
define('APP_URL', 'https://yoursite.infinityfreeapp.com');
```

### 6. Set up email (optional but recommended)
- Sign up free at https://www.brevo.com
- Get your API key from Settings → API Keys
- Edit `config/mail.php`:
```php
define('BREVO_API_KEY', 'your-actual-api-key');
define('MAIL_FROM',     'your@email.com');
```

### 7. Upload files
- In control panel → **File Manager** → open `htdocs` folder
- Upload ALL project files (everything in the P2P folder)
- Make sure `config/database.php` has the live values before uploading

### 8. Delete sensitive files on the server
After uploading, delete these from the server (they're not needed live):
- `migrate.php`
- `debug_notify.php` (if it exists)
- `DEPLOY.md`

### 9. Visit your site
Go to `https://yoursite.infinityfreeapp.com` — it should work.

---

## Step-by-Step: Railway (Most Reliable)

### 1. Create account
- Go to https://railway.app → Sign up with GitHub

### 2. New project → Deploy from GitHub
- Push your P2P folder to a GitHub repo
- In Railway → New Project → Deploy from GitHub repo

### 3. Add MySQL plugin
- In your Railway project → Add Plugin → MySQL
- Railway gives you the connection details automatically

### 4. Set environment variables
In Railway → Variables, add:
```
DB_HOST     = (from Railway MySQL plugin)
DB_NAME     = railway
DB_USER     = root
DB_PASS     = (from Railway MySQL plugin)
APP_URL     = https://your-app.railway.app
```

### 5. Import database
- Use Railway's MySQL plugin → Connect → run the SQL from `database.sql`

---

## Common Issues

**"Database connection failed"**
→ Check DB_HOST, DB_NAME, DB_USER, DB_PASS in config/database.php

**Images not showing**
→ Make sure `assets/images/` folder was uploaded with all files

**Redirects not working**
→ Make sure `.htaccess` was uploaded (it's a hidden file — enable "show hidden files" in your FTP client)

**Session issues**
→ Some free hosts require `session.save_path` to be set. Add to `.htaccess`:
```
php_value session.save_path "/tmp"
```

**OTP code not emailing**
→ Set up Brevo API key in `config/mail.php`. Until then, the code shows on screen.
