# HealthSphere — Deployment Guide (healthsphere.info)

## 1. Buy Domain & Hosting

| Step | Where |
|---|---|
| Buy domain `healthsphere.info` | Namecheap / GoDaddy / Hostinger |
| Buy hosting | **Hostinger Premium** (~£2.99/mo) — PHP 8.2, MySQL 8, free SSL |
| Point domain nameservers to Hostinger | Hostinger dashboard → Domains → Nameservers |

---

## 2. Set Up Hosting (cPanel)

1. Log into Hostinger hPanel → **Hosting** → **Manage**
2. Go to **Databases** → Create MySQL database + user → note the credentials
3. Go to **phpMyAdmin** → select the new database → **Import** → upload `sql/healthsphere.sql`
4. Run the migration files via browser after upload:
   - `https://healthsphere.info/sql/food_expansion_migration.php`
   - `https://healthsphere.info/sql/safe_appetite_migration.php`
   - `https://healthsphere.info/sql/breakfast_migration.php`
   - Delete migration files after running them!

---

## 3. Upload Files

**Option A — File Manager (easiest):**
1. Zip the entire HealthSphere folder (excluding `.git/`, `flutter_app/build/`)
2. hPanel → **File Manager** → open `public_html/`
3. Upload the zip → Extract into `public_html/` (files go directly in root, NOT in a subfolder)

**Option B — FTP (FileZilla):**
- Host: `ftp.healthsphere.info`
- Username/password: from hPanel → FTP Accounts
- Upload all files to `/public_html/`

---

## 4. Create Production Config Files

In hPanel **File Manager**, create these files in `public_html/config/`:

### `config/db.php`
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');   // from step 2
define('DB_PASS', 'your_db_password');   // from step 2
define('DB_NAME', 'your_db_name');       // from step 2

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database error']));
}
```

### `config/ai.php`
```php
<?php
define('ANTHROPIC_API_KEY',   'your_anthropic_key');
define('AI_MODEL',            'claude-haiku-4-5-20251001');
define('AI_MAX_TOKENS',       900);
define('SPOONACULAR_API_KEY', '98a0f1985030490d8e10d1abef00cd39');
```

### `config/mail.php`
```php
<?php
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      465);
define('MAIL_USER',      'Prajwalsk53@gmail.com');
define('MAIL_PASS',      'ynhe tlnv pokm qxyf');
define('MAIL_FROM_NAME', 'HealthSphere NHS');
define('MAIL_FROM',      'Prajwalsk53@gmail.com');
define('MAIL_ADMIN',     'Prajwalsk53@gmail.com');
define('APP_URL',        'https://healthsphere.info');
```

---

## 5. Enable HTTPS

1. hPanel → **SSL** → **Let's Encrypt** → Install for `healthsphere.info`
2. In `.htaccess`, **uncomment** the HTTPS redirect lines:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

---

## 6. Test Checklist

- [ ] `https://healthsphere.info` loads login page
- [ ] Demo login works (patient@healthsphere.com / password)
- [ ] Dashboard loads with data
- [ ] Diet Tracker → Log Meal → food search works (local DB)
- [ ] Diet Tracker → Log Meal → Spoonacular search returns results
- [ ] Safe Appetite → Scan Ingredients works
- [ ] Messages load
- [ ] Admin dashboard accessible (admin@healthsphere.com)
- [ ] SSL padlock shows in browser

---

## 7. After Deployment — Security

- Delete `setup.php` from the server (or block it in .htaccess)
- Delete all `sql/*.php` migration files from the server
- Confirm `.sql` and `.md` files are blocked (test: `healthsphere.info/sql/healthsphere.sql` should return 403)

---

## Automatic Environment Detection

The app auto-detects localhost vs live domain — **no code changes needed**.
- On `localhost` → uses `http://localhost/HealthSphere`
- On `healthsphere.info` → uses `https://healthsphere.info`

This is handled in `config/config.php` via `IS_LOCAL` and `BASE_PATH` constants.
