# Yapland Skikerst (PoC)

Eindejaarscampagne 2026: elke klant krijgt een eigen versie van de ski-out-of-office,
met logo, kleuren en kerstwens. Yappa zet de spellen op in een kleine admin.

## Lokaal opstarten

Zoals fitfeels: Symfony CLI + Docker voor de database.

```bash
cp .env.example .env          # .env gaat nooit mee in git
docker compose up -d          # MySQL 8.4, de Symfony CLI vult DATABASE_URL zelf in
symfony composer install
symfony console doctrine:migrations:migrate -n
symfony console doctrine:fixtures:load -n   # testaccount + twee voorbeeldklanten
symfony server:start -d
```

Admin: `/login`. Het lokale testaccount staat in `src/DataFixtures/AppFixtures.php`.
Een echt account (alleen `@yappa.be`): `symfony console app:user:create naam@yappa.be`.

## Hoe het werkt

- **Klant** (`App\Entity\Client`): naam, logo, drie kleuren, kerstwens, online of niet.
  De URL is `/ski/{token}` met 12 willekeurige tekens: geen klantnaam in URL's of bestandsnamen.
- **Spel** (`templates/ski/play.html.twig`, `assets/ski/game.js`): Twig geeft de instellingen
  door via een `data-config`-attribuut (automatisch ge-escaped). Het spel zelf is een
  plaatsvervanger tot we de code van de echte out-of-office hebben.
- **Scorebord** (`App\Entity\Score`): de server kiest een willekeurige naam per bezoeker
  (`App\Service\PlayerNames`), dus geen persoonsgegevens en geen filter nodig.
  Een score wordt geweigerd als hij sneller is dan het spel kan (max. 40 punten per seconde
  sinds het laden) en er gelden max. 20 scores per 10 minuten per IP-adres.
- **Einde campagne** (`app.campaign_ends_at` in `config/services.yaml`): vanaf 1 februari 2027
  geeft elk spel een 404. `symfony console app:scores:purge` wist dan alle scores
  (dagelijks via cron draaien; vóór die datum doet het niets).
- **Handtekening**: op de pagina van een klant staat een kant-en-klaar blok om te kopiëren
  en naar de klant te sturen.
- **Vindbaarheid**: elke spelpagina stuurt `X-Robots-Tag: noindex, nofollow`.

## Uitnodigingen en claimen

Klanten zetten hun spel zelf op, maar alleen met een uitnodiging: één spel per uitnodiging.

1. **Admin → Uitnodigingen**: CSV importeren (bedrijf + e-mailadres, `;` of `,`).
2. **Admin → Mail**: tekst aanpassen (`{bedrijf}` wordt ingevuld) en jezelf een testmail sturen.
3. **Verstuur**: de mails gaan in de wachtrij (Messenger, tabel `messenger_messages`) en de cron
   (`etc/crontab`, `messenger:consume async`) verstuurt ze via SendGrid.
4. De klant opent `/claim/{token}`, kiest logo, kleuren en kerstwens met een live voorbeeld, en
   publiceert. Daarna toont dezelfde link de spel-link en de handtekening, en kan de klant
   via `/claim/{token}/aanpassen` hetzelfde spel nog wijzigen. Eén uitnodiging maakt nooit een
   tweede spel. Vervangen logo's blijven bewaard, want eerder geplakte handtekeningen wijzen ernaar.
5. Claimen en aanpassen kan tot en met 1 december (`app.claim_ends_at`). Een uitnodiging intrekken of
   `/afmelden/{token}` maakt de link ongeldig. Elke mail heeft een `List-Unsubscribe`-header.

De cronjobs staan in `etc/crontab`. Elke deploy zet ze tussen markeringen in de crontab van het
Combell-account (`~/.crontab`, gecontroleerd met `crontab -T`), zonder de jobs van andere sites te raken; de vorige staat
daarna in `crontab.before-deploy` naast `www/`. Een `.crontab`-bestand in de subsite leest Combell niet.

Lokaal vangt Mailpit alle mails op: `symfony open:local:webmail`. Mails uit de wachtrij versturen:
`symfony console messenger:consume async -vv`.

Op de server in `deploy/shared/.env`:

```dotenv
MAILER_DSN=sendgrid+api://SENDGRID_API_KEY@default
MAIL_FROM=kerst@yappa.be        # optioneel, dit is de standaard
DEFAULT_URI=https://skigame.yappa.be
```

De basic auth geldt op de server alleen voor `/admin`, `/login` en `/logout`.

## Huisstijl

Yapland-tokens in `assets/styles/tokens.css` (kleuren, Unbounded + Epilogue), gedeeld door
admin en spel. De kleuren van de klant kleuren enkel wat van de klant is: skiër, vlaggen, knoppen.
