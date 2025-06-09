# Member Admin - WordPress Plugin

Ett WordPress-plugin för att anpassa användar-listan med ACF-fält, kompatibelt med WordPress 6.8.1.

## Funktioner

- **Anpassa användar-listan**: Lägg till ACF-fält som kolumner i användar-listan
- **Dropdown-val**: Välj fält baserat på upptäckta ACF-fält
- **Anpassa-knapp**: Enkel konfiguration direkt från användar-listan
- **Typanpassad visning**: Fälten presenteras enligt sin ACF-typ
- **Sorterbara kolumner**: Vissa fälttyper kan sorteras
- **Responsiv design**: Fungerar på både desktop och mobil

## Krav

- WordPress 6.8 eller senare
- PHP 8.0 eller senare
- Advanced Custom Fields (ACF) plugin

## Installation

1. Ladda upp plugin-filerna till `/wp-content/plugins/member-admin/` mappen
2. Aktivera pluginet genom 'Plugins' menyn i WordPress
3. Se till att Advanced Custom Fields (ACF) plugin är installerat och aktiverat
4. Skapa ACF-fältgrupper som är kopplade till användare

## Användning

1. Gå till **Användare** i WordPress admin
2. Klicka på **"Anpassa kolumner"** knappen högst upp på sidan
3. Välj vilka ACF-fält du vill visa som kolumner
4. Klicka **"Spara ändringar"**
5. Användar-listan kommer nu att visa de valda fälten som kolumner

## ACF-fälttyper som stöds

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

## Sorterbara fält

Följande fälttyper kan sorteras:
- Text
- Number
- Email
- Date Picker
- Date Time Picker
- Select
- Radio
- True/False

## Teknisk information

### Filstruktur
```
member-admin/
├── member-admin.php (huvudfil)
├── includes/
│   ├── class-acf-field-manager.php
│   ├── class-user-list-customizer.php
│   └── class-admin-interface.php
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

## Felsökning

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
- Kontrollera att PHP-versionen är 8.0 eller senare
- Se till att ACF är installerat

## Utveckling

Pluginet använder Singleton-pattern för alla huvudklasser och följer WordPress plugin development best practices.

### Hooks som används
- `manage_users_columns` - Lägger till kolumner
- `manage_users_custom_column` - Visar kolumninnehåll
- `manage_users_sortable_columns` - Gör kolumner sorterbara
- `pre_get_users` - Hanterar sortering

### AJAX-endpoints
- `member_admin_get_fields` - Hämtar tillgängliga fält
- `member_admin_update_fields` - Uppdaterar aktiverade fält

## Licens

GPL v2 or later

## Support

För support, skapa en issue i projektets repository eller kontakta utvecklaren. 