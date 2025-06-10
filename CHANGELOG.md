# Changelog

Alla viktiga ändringar i detta projekt dokumenteras i denna fil.

Formatet är baserat på [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
och detta projekt följer [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Tillagt
- Första versionen av Member Admin plugin
- Automatisk upptäckt av ACF-fält för användare
- Modal-gränssnitt för att välja vilka fält som ska visas
- Stöd för alla vanliga ACF-fälttyper (text, nummer, datum, select, etc.)
- Sorterbara kolumner med korrekt hantering av tomma värden
- Responsiv design som fungerar på mobil och desktop
- Caching för bättre prestanda
- Säkerhetsåtgärder (nonce-verifiering, behörighetskontroll)
- Internationalisering (i18n) stöd
- Kompatibilitet med WordPress 6.8+ och PHP 7.4+
- Grundläggande plugin-struktur
- ACF-fälthantering
- Användarlistanpassning

### Fixat
- Problem med sortering av datum-fält över flera sidor
- JavaScript-fel när inga fält har valts
- Felaktig felhantering vid sparning av oförändrade inställningar

## Versionsnummer Format

Vi använder [Semantic Versioning](https://semver.org/):

- **MAJOR.MINOR.PATCH** (1.0.0)
- **MAJOR** version för inkompatibla API-ändringar
- **MINOR** version för nya funktioner (bakåtkompatibla)
- **PATCH** version för buggfixar (bakåtkompatibla)

## Kategorier

- **Tillagt** - för nya funktioner
- **Ändrat** - för ändringar i befintliga funktioner
- **Föråldrat** - för funktioner som snart kommer att tas bort
- **Borttaget** - för funktioner som har tagits bort
- **Fixat** - för buggfixar
- **Säkerhet** - för säkerhetsrelaterade ändringar 