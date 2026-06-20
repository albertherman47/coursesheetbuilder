# CourseSheet Builder

Laravel-alapú tantervi adatlapkezelő rendszer Filament adminisztrációs felülettel. Segítségével egyszerűen kezelhetők a szakok, tantárgyak, tanévek, oktatók és a hozzájuk tartozó tantárgyleírások (szillabusok), valamint generálhatók belőlük hivatalos DOCX formátumú dokumentumok.

---

## 🛠️ Rendszerkövetelmények

A projekt futtatásához szükséges szoftverek:
- **PHP 8.3+** (aktív `pdo_sqlite` és `zip` kiterjesztésekkel)
- **Composer** (PHP függőségkezelő)
- **Node.js (v18+) & NPM**
- **Git**

---

##  Gyors Telepítés (3 egyszerű lépés)

1. **Klónozd és lépj be a mappába:**
   ```bash
   git clone <repo-url>
   cd coursesheetbuilder
   ```
2. **Futtasd az automatikus beállítást:**
   ```bash
   composer setup
   ```
3. **Telepítsd a kiindulási mintaadatokat:**
   ```bash
   php artisan db:seed
   ```

*(Megjegyzés: Ha manuálisan szeretnéd telepíteni lépésről lépésre, lásd a [Manuális Telepítés](#-manuális-telepítés-haladó) részt.)*

---

## 📂 Minta / Tesztadatok (JSON)

A projekt gyökérkönyvtárában előre elkészített tantervi tesztadatok találhatók JSON formátumban:
- `curriculum_data.json` – Gazdasági informatika szak (2025/26 tanév)
- `curriculum_data_2024_25.json` – Gazdasági informatika szak (2024/25 tanév)
- `curriculum_data_2023_24.json` – Gazdasági informatika szak (2023/24 tanév)
- `marketing_curriculum_data.json` – Marketing szak (2025/26 tanév)

A JSON fájlok felépítése tartalmazza a tanterv alapadatát (szak megnevezése, tanév), valamint a tantárgyak részletes adatait (kódok, kreditek, óraszámok, román/angol megnevezések és a kimeneti követelmények).

---

## 📥 Tanterv Importőr (CurriculumImporter)

A rendszer egy beépített `CurriculumImporter` szolgáltatással (`App\Services\CurriculumImporter`) rendelkezik, amellyel tetszőleges JSON tanterv betölthető az adatbázisba.

### Hogyan használd?

A terminálból a `curriculum:import` parancs segítségével importálhatod a JSON fájlokat.

**Fontos:** Az importálás futtatása előtt a `php artisan db:seed` parancsot már futtatnod kellett, hogy a tanszékek, a programok (szakok) és a tanárok be legyenek regisztrálva!

#### 1. Új tanterv importálása:
```bash
php artisan curriculum:import curriculum_data.json
```

#### 2. Meglévő tanterv felülírása és újraimportálása (`--force` opcióval):
Ha már létezik az adott tanévhez tartozó tanterv az adatbázisban, a rendszer kihagyja azt. A felülíráshoz használd a `--force` kapcsolót:
```bash
php artisan curriculum:import curriculum_data.json --force
```

#### Futtatás Tinker-en keresztül (programozottan):
Ha PHP kódból szeretnéd meghívni az importőrt:
```bash
php artisan tinker
```
Majd a Tinker konzolban:
```php
app(App\Services\CurriculumImporter::class)->importFromFile(base_path('curriculum_data.json'), true);
```

---

## 💻 Fejlesztői szerver indítása

Indítsd el a lokális szervereket:
```bash
composer dev
```
Ez elindítja a Laravel szervert (http://127.0.0.1:8000), a Vite dev szervert, a queue munkamenetet és a log figyelőt.

---

## 🔑 Belépés az Admin Panelre

- **URL:** [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)
- **E-mail:** `admin@admin.com`
- **Jelszó:** `password`

---

## 🛠️ Manuális Telepítés (Haladó)

Ha a `composer setup` nem futtatható a környezetedben, végezd el a beállítást lépésről lépésre:

1. **Composer csomagok telepítése:**
   ```bash
   composer install
   ```
2. **Környezeti fájl létrehozása:** másold át a `.env.example`-t `.env` néven.
3. **App kulcs generálása:**
   ```bash
   php artisan key:generate
   ```
4. **Adatbázis fájl létrehozása:**
   - **Linux/macOS:** `touch database/database.sqlite`
   - **Windows (PowerShell):** `New-Item -ItemType File -Path database/database.sqlite -Force`
   - **Windows (CMD):** `type nul > database\database.sqlite`
5. **Migráció és Seeding:**
   ```bash
   php artisan migrate --seed
   ```
6. **NPM függőségek és Build:**
   ```bash
   npm install
   npm run build
   ```
