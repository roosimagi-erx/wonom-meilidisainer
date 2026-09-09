=== Wonom Meilidisainer ===
Contributors: wonomdigital
Tags: woocommerce, email, template, editor, transactional
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce'i tellimusmeilide visuaalne kujundaja. Bränd seadistatakse üks kord ja rakendub kõigile meilidele.

== Kirjeldus ==

Wonom Meilidisainer annab WooCommerce'i transaktsioonimeilidele kaubamärgi näo, ilma et peaks malle kopeerima või koodi kirjutama.

**Põhimõte, mis eristab teistest meiliehitajatest:** sa ei ehita 14 meili eraldi kokku. Sa seadistad ühe korra brändi — logo, värvid, kiri, nupustiil, päis ja jalus — ja see rakendub automaatselt kõigile meilidele. Ainult siis, kui mõni konkreetne meil vajab midagi lisaks (tervitus, nupp, tarneinfo), lisad sinna plokke.

= Mida saab teha =

* **Bränd** — logo, värvipalett, kirjatüüp ja -suurus, nupustiil, meili laius, nurkade ümarus, sisemine veeris, lisa-CSS.
* **Päis ja jalus** — kõigile meilidele ühine, ehitatakse plokkidest.
* **Meilipõhine sisu** — igale meilile eraldi pealkiri postkastis, suur pealkiri ja plokid enne või pärast tellimuse tabelit.
* **Plokid** — pealkiri, tekstilõik, nupp, pilt, joon, tühi ruum, kaks veergu, sotsiaalmeedia, oma HTML.
* **Muutujad** — `{{customer_first_name}}`, `{{order_number}}`, `{{order_total}}`, `{{order_url}}` jt, valikuna nupu alt.
* **Elav eelvaade** — muudatus on ekraanil kohe, arvuti- ja mobiilivaade.
* **Serveri eelvaade** — renderdab meili sama koodiga, mis päris saatmisel, viimase tellimuse andmetega.
* **Testmeil** — saadab päris WooCommerce'i meili, aga suunab selle testaadressile.
* **Üks lüliti** — „Kujundus sees" lülitab kogu ülevõtmise korraga välja, WooCommerce'i vaikemallid tulevad tagasi.

= Kaetud meilid =

Tellimus töösse võetud, täidetud, ootel, tagastatud, arve/makseootel, märkus kliendile, parooli lähtestamine, uus konto, uus tellimus poele, tühistatud tellimus, ebaõnnestunud tellimus.

= Kuidas see töötab =

Plugin asendab WooCommerce'i mallid `emails/email-header.php` ja `emails/email-footer.php` ning lisab brändi CSS-i filtriga `woocommerce_email_styles`. Tellimuse tabeli ja aadressid renderdab endiselt WooCommerce ise — nii ei lähe midagi kaotsi, kui WooCommerce neid malle uuendab.

Kui teema on need mallid ise üle kirjutanud, jätab plugin teema oma alles ega sekku.

== Paigaldus ==

1. Laadi kaust `wonom-meilidisainer` üles kausta `/wp-content/plugins/`.
2. Aktiveeri plugin WordPressi pluginate lehel.
3. Ava **WooCommerce → Meilidisainer**.

== Korduma kippuvad küsimused ==

= Kas olemasolevad WooCommerce'i meiliseaded jäävad alles? =

Jah. Meilide sisse-/väljalülitamine, saajad ja vaiketeemad jäävad WooCommerce'i seadetesse. Meilidisainer kirjutab teema üle ainult siis, kui oled selle välja täitnud.

= Kas plugin muudab tellimuse tabelit? =

Ei, tabeli renderdab WooCommerce. Meilidisainer annab sellele ainult brändi värvid ja kirja.

= Mis juhtub, kui plugin välja lülitada? =

Meilid lähevad tagasi WooCommerce'i vaikekujundusele. Kujundus jääb andmebaasi alles.

== Muudatused ==

= 0.10.0 =
* Uus: iga meili juures saab valida „terve meil ise". Siis ei kasutata WooCommerce'i sisumalli üldse ja kogu kiri pannakse kokku plokkidest.
* Uued plokid: tellimuse tabel, aadressid, kliendi märkus ja tellimuse väli. Tellimuse tabeli ja aadressid renderdab endiselt WooCommerce, nii et maksuread, allahindlused ja tarnepluginate lisad jäävad alles.
* Uus: plokk „Tellimuse väli" toob meili suvalise tellimuse välja, näiteks paki jälgimiskoodi. Sildi, valikulise lingi (kasuta {{value}}) ja tühja välja peitmisega.
* Uus märgend {{meta:võti}}, millega saab tellimuse välju kasutada ka tekstis, nupu lingis ja oma HTML-is.
* Uuendusi saab nüüd paigaldada otse kujundajast — eraldi Pluginad-lehele minna pole vaja.

= 0.9.1 =
* Parandus: meilipõhised plokid (sisu enne ja pärast tellimuse tabelit) jäid päris meilist välja. WooCommerce ei anna päise- ja jalusemallile meiliobjekti kaasa, seega ei osanud plugin öelda, millise meiliga on tegu. Nüüd püütakse meil kinni tegevustest woocommerce_email_header ja woocommerce_email_footer.
* Parandus: mallidel puudus @version päis, mistõttu WooCommerce → Olek → Mallid märkis need punaselt.
* Kujundaja eelvaade näitab nüüd WooCommerce'i päris pealkirja, mitte meili nime. Pealkirja ja teema väljade kohatäitjad näitavad poe tegelikke vaikeväärtusi.

= 0.9.0 =
* Esimene testversioon: bränd, päis, jalus, meilipõhised plokid, elav eelvaade, serveri eelvaade, testmeil.
