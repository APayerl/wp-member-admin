# Member Admin - WordPress Plugin

![WordPress](https://img.shields.io/badge/WordPress-6.8+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/License-GPL--2.0-green.svg)

Ett öppet källkods WordPress-plugin för att anpassa användar-listan med ACF-fält, kompatibelt med WordPress 6.8.1.

## 🚀 Funktioner

- ✅ **Anpassa användar-listan**: Lägg till ACF-fält som kolumner i användar-listan
- ✅ **Dropdown-val**: Välj fält baserat på upptäckta ACF-fält
- ✅ **Anpassa-knapp**: Enkel konfiguration direkt från användar-listan
- ✅ **Typanpassad visning**: Fälten presenteras enligt sin ACF-typ
- ✅ **Sorterbara kolumner**: Vissa fälttyper kan sorteras
- ✅ **Responsiv design**: Fungerar på både desktop och mobil

## 📋 Krav

- WordPress 6.8 eller senare
- PHP 7.4 eller senare
- Advanced Custom Fields (ACF) plugin

## 📦 Installation

### Via WordPress Admin (Rekommenderat)
1. **Ladda ner** senaste [release](https://github.com/apayerl/wp-member-admin/releases) som ZIP-fil
2. Gå till **Plugins** → **Lägg till nytt** → **Ladda upp plugin**
3. Välj ZIP-filen och klicka **"Installera nu"**
4. **Aktivera** pluginet
5. Se till att **Advanced Custom Fields (ACF)** är installerat och aktiverat

### Via FTP/Filhanterare
1. Ladda upp plugin-filerna till `/wp-content/plugins/member-admin/` mappen
2. Aktivera pluginet genom 'Plugins' menyn i WordPress
3. Se till att Advanced Custom Fields (ACF) plugin är installerat och aktiverat

### Via Git (För utvecklare)
```bash
cd /wp-content/plugins/
git clone https://github.com/apayerl/wp-member-admin.git
```

## 🎯 Användning

1. Gå till **Användare** i WordPress admin
2. Klicka på **"Anpassa kolumner"** knappen högst upp på sidan
3. Välj vilka ACF-fält du vill visa som kolumner
4. Klicka **"Spara ändringar"**
5. Användar-listan kommer nu att visa de valda fälten som kolumner

## 🔧 ACF-fälttyper som stöds

Pluginet stöder alla vanliga ACF-fälttyper:

- Text, Textarea, Email, URL
- Number (med numerisk formatering)
- Select, Radio, Checkbox
- True/False
- Date Picker, Time Picker, Date Time Picker
- Image (visas som miniatyr)
- File (visas som nedladdningslänk)
- User (visar användarnamn)
- Post Object (visar inläggstitel)

## 📊 Sorterbara fält

Följande fälttyper kan sorteras:
- Text
- Number
- Email
- Date Picker
- Date Time Picker
- Select
- Radio
- True/False

## 🏗️ Teknisk information

### Filstruktur
```
member-admin/
├── member-admin.php (huvudfil)
├── includes/
│   ├── class-acf-field-manager.php
│   ├── class-user-list-customizer.php
│   └── class-admin-interface.php
├── languages/
│   └── member-admin.pot
└── README.md
```

### Kodstandarder
- Följer WordPress kodstandarder
- Använder specifika typer istället för `var`
- Implementerar SOLID-principer
- DRY (Don't Repeat Yourself) och SoC (Separation of Concerns)
- Fail-fast för konfiguration

### Säkerhet
- Nonce-verifiering för AJAX-anrop
- Capability-kontroll (`manage_options`)
- Escaping av utdata
- Sanitering av indata

## 🐛 Felsökning

### Inga fält visas i dropdown
- Kontrollera att ACF är installerat och aktiverat
- Se till att du har skapat fältgrupper som är kopplade till användare
- Fältgrupper måste ha location rules som matchar "User Form" eller "User Role"

### Fält visas inte i användar-listan
- Kontrollera att fälten är sparade i anpassa-dialogen
- Ladda om sidan efter att ha sparat inställningar
- Se till att användarna har värden för de valda fälten

### Plugin fungerar inte
- Kontrollera att WordPress-versionen är 6.8 eller senare
- Kontrollera att PHP-versionen är 7.4 eller senare
- Se till att ACF är installerat

### Avinstallation
- **Avaktivera**: Temporär cache rensas
- **Avinstallera**: Alla plugin-data rensas permanent, inklusive:
  - Plugin-inställningar (`member_admin_settings`)
  - Donation-banner inställningar per användare
  - Cache och transients
  - Installationsdatum

## 🛠️ Utveckling

Pluginet använder Singleton-pattern för alla huvudklasser och följer WordPress plugin development best practices.

### Kör lokalt
```bash
git clone https://github.com/apayerl/wp-member-admin.git
cd member-admin
# Kopiera till din WordPress installation
cp -r . /path/to/wordpress/wp-content/plugins/member-admin/
```

### Hooks som används
- `manage_users_columns` - Lägger till kolumner
- `manage_users_custom_column` - Visar kolumninnehåll
- `manage_users_sortable_columns` - Gör kolumner sorterbara
- `pre_get_users` - Hanterar sortering

### AJAX-endpoints
- `member_admin_get_fields` - Hämtar tillgängliga fält
- `member_admin_update_fields` - Uppdaterar aktiverade fält

## 🤝 Bidra

Detta är ett hobbyproject som utvecklas på fritiden. Om du vill bidra:

1. Fork projektet
2. Skapa en feature branch (`git checkout -b feature/ny-funktion`)
3. Committa dina ändringar (`git commit -am 'Add: Ny funktion'`)
4. Pusha till branchen (`git push origin feature/ny-funktion`)
5. Öppna en Pull Request

Kom ihåg att detta är ett sidoprojekt, så respektera att svarstider kan variera! 🙂

## 📝 Changelog

### [1.0.0] - 2024-XX-XX
- Initial release
- Anpassa användar-listan med ACF-fält
- Modal interface för fältval
- Stöd för alla vanliga ACF-fälttyper
- Sorterbara kolumner

## 📄 Licens

GPL v2 or later - se [LICENSE](LICENSE) fil för detaljer.

## 💬 Community

- 🐛 **Hittat en bug?**: Rapportera gärna via [GitHub Issues](https://github.com/apayerl/wp-member-admin/issues)
- 💡 **Förslag på förbättringar?**: Dela dina idéer via [GitHub Issues](https://github.com/apayerl/wp-member-admin/issues)
- 🤝 **Bidra**: Detta är ett öppet källkodsprojekt - alla bidrag är välkomna!

## ⭐ Gillar du pluginet?

Om Member Admin hjälper dig, ge det gärna en stjärna på GitHub! ⭐  
Alla bidrag och feedback uppskattas i detta öppna källkodsprojekt.

## ☕ Stöd projektet

Detta plugin utvecklas på fritiden som ett hobby-projekt. Om det sparar tid för dig och du vill visa uppskattning:

[![Buy Me A Coffee](https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png)](https://buymeacoffee.com/payerl)

[![Thanks.dev](https://img.shields.io/badge/Thanks.dev-💝-black)](https://thanks.dev/apayerl)

Varje bidrag motiverar till fortsatt utveckling! 🙂 