# cPanel Deployment & Upload Guide
**John Luka Media (JLM) Digital Ecosystem**  
**Target Domains**:
- **ZAVORA Marketplace**: `https://zavora.jlm.com.ng`
- **JLM Corporate Platform**: `https://jlm.com.ng`
- **Learnerium LMS**: `https://learnerium.jlm.com.ng`

---

## 📦 What's Inside the Deployment Folder

Location: `/Applications/MAMP/htdocs/cpanel_deploy/`

| File | Size | Purpose | Destination on cPanel |
| :--- | :--- | :--- | :--- |
| **`zavora_v1.0_cpanel_upload.zip`** | ~94 KB | Complete standalone ZAVORA application (Storefront, multi-vendor hub, variants engine, Paystack checkout) | `/home/gwylvxeo/zavora.jlm.com.ng/` *(or Document Root for subdomain)* |
| **`zavora_database_setup.sql`** | ~24 KB | Full MySQL schema + pre-seeded catalog with 33 variants & vendor accounts | Import into phpMyAdmin under `gwylvxeo_zavora` |
| **`learnerium_ecosystem_patch.zip`** | ~72 KB | Updated files for Learnerium (SSO gateway, token API, Blade PHP 7.4 hardening) | `/home/gwylvxeo/learnerium.jlm.com.ng/` |
| **`jlm_corporate_patch.zip`** | ~50 KB | Updated files for JLM corporate site (marketplace bridge, ecosystem links, .htaccess) | `/home/gwylvxeo/public_html/` |

---

## 🚀 Step 1: Set Up Subdomain for ZAVORA

1. Log into your **cPanel** dashboard (`https://jlm.com.ng:2083`).
2. Search for **Domains** or **Subdomains**.
3. Create a new subdomain:
   - **Subdomain**: `zavora`
   - **Domain**: `jlm.com.ng`
   - **Document Root**: `zavora.jlm.com.ng` (or `public_html/zavora`)
4. Click **Create**.
5. *(Optional)* Go to **SSL/TLS Status** in cPanel and click **Run AutoSSL** to ensure `zavora.jlm.com.ng` has an active SSL padlock certificate.

---

## 🗄️ Step 2: Create MySQL Database & Import Data

1. In cPanel, open **MySQL® Databases**.
2. **Create New Database**:
   - Name: `zavora` (Full database name: `gwylvxeo_zavora`).
   - Click **Create Database**.
3. **Add New User**:
   - Username: `zavora` (or use existing user `gwylvxeo_gwylvxeo`).
   - Password: Generate a secure password and copy it down.
   - Click **Create User**.
4. **Add User to Database**:
   - Select User: `gwylvxeo_zavora`
   - Select Database: `gwylvxeo_zavora`
   - Click **Add** -> Check **ALL PRIVILEGES** -> Click **Make Changes**.
5. Open **phpMyAdmin** from cPanel:
   - Click on `gwylvxeo_zavora` in the left sidebar.
   - Click **Import** in the top navigation tab.
   - Click **Choose File** and select `zavora_database_setup.sql`.
   - Click **Import / Go** at the bottom.
   - *Result*: All 7 tables (`categories`, `vendors`, `products`, `product_variants`, `orders`, `order_items`, `vendor_payouts`) are created and pre-populated!

---

## 📤 Step 3: Upload & Extract ZAVORA via File Manager

1. In cPanel, open **File Manager**.
2. Ensure hidden files are visible:
   - Click **Settings** (top-right corner).
   - Check **Show Hidden Files (dotfiles)**.
   - Click **Save**.
3. Navigate to the document root for your subdomain (e.g. `/home/gwylvxeo/zavora.jlm.com.ng/`).
4. Click **Upload** (top toolbar) and drag & drop **`zavora_v1.0_cpanel_upload.zip`**.
5. Once the upload bar turns green (100%), return to File Manager.
6. Right-click `zavora_v1.0_cpanel_upload.zip` and select **Extract** -> **Extract Files**.
7. Delete the uploaded `.zip` file to save space.

---

## ⚙️ Step 4: Configure Production `.env` (2 Easy Options)

### Option A (Recommended): Use the Built-in Web Configurator
1. In your browser, navigate to:  
   **`https://zavora.jlm.com.ng/configure_env.php`**
2. The page will auto-detect your setup:
   - **Database Host**: `localhost` (default)
   - **Port**: `3306`
   - **Database Name**: `gwylvxeo_zavora`
   - **Database User**: `gwylvxeo_zavora`
   - **Database Password**: Enter the MySQL password you created in Step 2.
3. Click **"Test Connection & Update .env"**.  
   The script immediately tests the connection with MySQL. If successful, it writes the credentials to `.env` and creates a backup!
4. Click the red button: **"🗑 Delete This Script for Security"** so the file is removed from the server.

### Option B: Terminal / SSH CLI Execution
In cPanel Terminal or SSH:
```bash
cd /home/gwylvxeo/zavora.jlm.com.ng
php configure_env.php --name=gwylvxeo_zavora --user=gwylvxeo_zavora --pass="YOUR_PASSWORD"
```

### Option C: Manual Edit via File Manager
1. In File Manager, ensure **Show Hidden Files (dotfiles)** is enabled.
2. Edit `.env` (or rename `env.production.txt` to `.env`):
   ```env
   ZAVORA_DB_HOST=localhost
   ZAVORA_DB_PORT=3306
   ZAVORA_DB_NAME=gwylvxeo_zavora
   ZAVORA_DB_USER=gwylvxeo_zavora
   ZAVORA_DB_PASS=YOUR_ACTUAL_DB_PASSWORD_HERE
   ```

---

## 🔄 Step 5: (Optional) Apply Updates to Learnerium & JLM

### To update Learnerium:
1. In File Manager, navigate to `/home/gwylvxeo/learnerium.jlm.com.ng/`.
2. Upload and extract `learnerium_ecosystem_patch.zip`.
3. In cPanel Terminal (or SSH):
   ```bash
   cd /home/gwylvxeo/learnerium.jlm.com.ng
   php artisan view:clear
   php artisan route:clear
   php artisan config:clear
   ```

### To update JLM Corporate:
1. In File Manager, navigate to `/home/gwylvxeo/public_html/`.
2. Upload and extract `jlm_corporate_patch.zip`.

---

## ✅ Step 6: Verification

Visit your live site:
1. **Storefront**: Navigate to `https://zavora.jlm.com.ng`
2. **Product Variants**: Click on **Apple iPhone 15 Pro Max** or **MacBook Air 15" M3** to test colour, size, and storage switches.
3. **Vendor Hub**: Visit `https://zavora.jlm.com.ng/seller/`
   - Test login with: `vendor@zavora.com.ng` / `password123`
   - Test bank payout selector (18 Nigerian banks supported) and 1-click product boost.
