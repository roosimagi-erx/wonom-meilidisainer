# Wonom Meilidisainer

WooCommerce'i tellimusmeilide visuaalne kujundaja. Bränd seadistatakse üks kord
ja rakendub kõigile meilidele — plokke lisad ainult sinna, kus neid päriselt vaja on.

**Olek:** testversioon 0.9.0 · **Nõuab:** WordPress 6.0+, PHP 7.4+, WooCommerce 6.0+

---

## Miks

Enamik meiliehitajaid paneb sind iga transaktsioonimeili eraldi kokku panema —
neliteist meili, neliteist korda sama tööd. Siin on kaks taset:

**1. Bränd.** Logo, värvipalett, kirjatüüp, nupustiil, meili laius, päis ja jalus.
Seadistad üks kord, rakendub automaatselt kõigile meilidele. Enamikul poodidest
lõpeb töö siin.

**2. Meilid.** Ainult siis, kui mõni konkreetne meil vajab midagi lisaks: oma
pealkiri postkastis, oma suur pealkiri, plokid enne või pärast tellimuse tabelit.

Tellimuse tabeli, aadressid ja kliendi andmed renderdab endiselt WooCommerce ise.
Plugin annab neile ainult brändi värvid ja kirja. Nii ei lähe midagi katki, kui
WooCommerce oma malle uuendab.

## Mida saab teha

- **Kaks režiimi meili kohta** — kas plokid WooCommerce'i sisu ümber, või
  „terve meil ise", kus kogu kiri pannakse kokku plokkidest ja WooCommerce'i
  sisumalli ei kasutata.
- **Plokid** — pealkiri, tekstilõik, nupp, pilt, joon, tühi ruum, kaks veergu,
  sotsiaalmeedia, oma HTML. Lohistamisega järjestatavad.
- **Oma tooteplokk** — „Tooted (oma tabel)" ja „Kokkuvõte (oma tabel)": sina
  valid veerud ja read, nende järjekorra ja sildid. Andmed tulevad päris
  tellimuselt, kujundus on sinu.
- **WooCommerce'i plokid** — tellimuse tabel, aadressid, kliendi märkus ja
  makseviisi juhised. Neid renderdab WooCommerce ise, seega maksuread,
  allahindlused ja makselahenduste lisandused jäävad alles. Kasuta neid siis,
  kui tahad WooCommerce'i vaikevälimust, ja oma plokke siis, kui tahad kontrolli.
- **Makseviiside juhised** — eraldi vahekaart, kus iga makseviisi juurde saab
  kirjutada oma teksti (nt pangaülekande rekvisiidid). Plokk „Makseviisi
  juhised (oma tekst)" toob kirja alati selle tellimuse makseviisi teksti.
- **Tellimuse väli** — toob meili suvalise tellimuse välja, näiteks paki
  jälgimiskoodi, koos sildi ja valikulise lingiga (`{{value}}` asendub
  väärtusega). Tühja välja saab automaatselt peita.
- **Elav eelvaade, mis ei valeta** — muudatus on ekraanil kohe, arvuti- ja
  mobiilivaade, klõps valib ploki. WooCommerce'i enda sisu tuuakse serverist
  päris kujul, seega dubleeriv tekst on kohe näha. WooCommerce'i ala on
  märgistatud ja selle küljes on nupp „Võta üle".
- **Muutujad** — `{{customer_first_name}}`, `{{order_number}}`, `{{order_total}}`,
  `{{order_url}}` jt, valikuna nupu alt. Lisaks `{{meta:võti}}`, millega saab
  igasse teksti tuua suvalise tellimuse välja.
- **Eelvaate tellimus** — vali ülaribalt, millise päris tellimuse andmetega
  kirja näidatakse. Nii saab kontrollida, kuidas meil eri makseviiside puhul
  välja näeb. Sama valik kehtib testmeili saatmisel.
- **Makseviisipõhine nähtavus** — igale plokile saab öelda, milliste
  makseviiside puhul see kirja läheb. Nii saab pangaülekande juhised panna
  ainult ülekandega tellimustele.
- **Serveri eelvaade** — renderdab sama koodiga, mis päris saatmine, valitud
  tellimuse andmetega. Režiim jääb sisse ja järgneb meili- ning
  tellimusevalikule, nii näed kohe, kas brauseri eelvaade ja päris meil kattuvad.
- **Testmeil** — saadab päris WooCommerce'i meili, aga suunab saaja
  testaadressile. Klient ei saa midagi.
- **Automaatsed uuendused** GitHubi väljalasetest, ilma WordPress.org-ita.
  Uue versiooni saab paigaldada otse kujundajast, Pluginad-lehele minemata.

## Kaetud meilid

| Rühm | Meilid |
|---|---|
| Kliendi tellimusmeilid | töösse võetud, täidetud, ootel, tagastatud, arve/makseootel, märkus kliendile |
| Konto meilid | parooli lähtestamine, uus konto |
| Poe sisemised | uus tellimus, tühistatud tellimus, ebaõnnestunud tellimus |

Nimekirja saab laiendada filtriga `wmd_email_list`.

## Paigaldus

1. Laadi alla viimane `wonom-meilidisainer.zip` [väljalasete lehelt](../../releases).
2. WordPressis: **Pluginad → Lisa uus → Laadi plugin üles**.
3. Ava **WooCommerce → Meilidisainer**.

Automaatsete uuenduste jaoks: vahekaart **Uuendused** → allikas *GitHubi väljalase*,
hoidla `kasutaja/wonom-meilidisainer`. Privaatse hoidla puhul lisa fine-grained
token õigusega *Contents: Read-only*.

## Kuidas see töötab

Plugin asendab WooCommerce'i mallid `emails/email-header.php` ja
`emails/email-footer.php` ning lisab brändi CSS-i filtriga
`woocommerce_email_styles`. Kui teema on need mallid ise üle kirjutanud, jätab
plugin teema oma alles ega sekku.

```
includes/
  helpers.php             brändi skeem, plokitüübid, meilide nimekiri
  class-wmd-design.php    kujunduse hoidla ja puhastus (üks option)
  class-wmd-tags.php      muutujad ja nende asendamine
  class-wmd-render.php    plokid -> meilikõlblik HTML (tabelid + inline CSS)
  class-wmd-emails.php    WooCommerce'i mallide ülevõtmine
  class-wmd-admin.php     halduslehe kest
  class-wmd-ajax.php      salvestus, eelvaade, testmeil, uuendused
  class-wmd-updater.php   uuendused GitHubi väljalasetest
templates/emails/         email-header.php, email-footer.php
assets/
  renderer.js             sama renderdusloogika brauseris (elav eelvaade)
  admin.js                kujundaja liides
  admin.css               kujundaja stiil
```

Renderdajaid on kaks: PHP oma teeb päris meilid, JS oma teeb kujundaja eelvaate.
Uue plokitüübi lisamisel tuleb see panna mõlemasse — `class-wmd-render.php` ja
`renderer.js` on teadlikult ühesuguse ülesehitusega. Nupp „Serveri eelvaade"
ongi selleks, et kontrollida, kas need kaks on sünkroonis.

Kogu kujundus elab ühes optionis (`wmd_design`) JSON-struktuurina. Kõik väljad
käivad salvestamisel läbi tüübipõhise puhastuse; oma HTML ja lisa-CSS kaotavad
skriptid ja `on*`-atribuudid.

## Ohutusklapid

- **„Kujundus sees" lüliti** ülaribal lülitab kogu ülevõtmise korraga välja —
  meilid lähevad tagasi WooCommerce'i vaikekujundusele, kujundus jääb alles.
- **Teema mallid võidavad** — juba olemasolevat kohandust ei kirjutata üle.
- **Testmeil suunab saaja ümber** filtriga `woocommerce_email_recipient_*`.

## Laienduskohad

| Filter | Mida teeb |
|---|---|
| `wmd_email_list` | lisab või eemaldab meile |
| `wmd_tags` | lisab oma muutujaid |

## Väljalase

```powershell
.\build-release.ps1 -Version 0.9.1 -Tag
```

Tõstab versiooni plugina failis ja `readme.txt`-s, ehitab ZIP-i, teeb commiti ja
sildi ning saadab GitHubi. Seejärel **Releases → Draft a new release**, vali silt,
lisa ZIP, avalda. Versiooninumber failis ja sildil peavad kattuma, muidu WordPress
uuendust ei näe.

## Teadaolevad piirangud

- Üks kujundus kõigile keeltele — WPML/Polylangi tuge veel ei ole.
- Tumeda režiimi käitumine postkastides on testimata.
- Kujunduse eksport/import JSON-ina on tegemata, kuigi struktuur seda toetab.
- Valmiskujundusi („templates") ei ole, on üks vaikekujundus.

## Litsents

GPL-2.0-or-later
