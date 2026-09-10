=== Wonom Meilidisainer ===
Contributors: wonomdigital
Tags: woocommerce, email, template, editor, transactional
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.19.0
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

= 0.19.0 =
* „Terve meil ise" alustab nüüd WooCommerce'i praeguse sisuga, mitte tühja lehega. Kiri võetakse plokkideks lahti — pealkirjad, tekstilõigud, tellimuse tabel, kokkuvõte ja aadressid — ja edasi saab neid tavaliste plokkidena muuta. Sinu enda plokid jäävad õigetesse kohtadesse ümber.
* Parandus: kontomeilidel (uus konto, parooli lähtestamine) näitas kujundaja näidissisu tellimuse tabeliga. Neil meilidel ei olegi tellimust, aga WooCommerce'i sisu on olemas — nüüd renderdatakse see päriselt, koos kasutajanime ja kontolinkidega.

= 0.18.1 =
* Uus: iga meili juures on lüliti „WooCommerce'i lisatekst kirja lõpus", vaikimisi väljas. Seda teksti („Thanks for shopping with us.") ei saa WooCommerce'i seadetes tühjendada — WC_Settings_API asendab tühja välja vaikeväärtusega ja tekst tuleb tagasi. Nüüd saab selle päriselt välja lülitada.
* Lüliti kehtib mõlemas režiimis ja mõjutab ühtviisi nii päris kirja kui kujundaja eelvaadet.
* Parandus: seadete muutmine, mis mõjutab serveri vastust, ei visanud eelvaate vahemälu ära, mistõttu lüliti mõju ei olnud kujundajas kohe näha.

= 0.18.0 =
* Uus: kujunduse eksport ja import. Kogu seadistus — bränd, päis, jalus, kõik meilid ja makseviiside juhised — ühes JSON-failis. Teises poes impordid selle ja oled kohe sama seadistusega.
* Import võtab vastu nii allalaaditud faili kui kleebitud JSON-i, küsib enne ülekirjutamist kinnitust ja laseb sisendi läbi sama puhastuse mis tavaline salvestamine.
* Vahekaart „Uuendused" on nüüd „Seaded" ja sisaldab kaht rühma: kujunduse eksport/import ning automaatsed uuendused.

= 0.17.2 =
* Parandus: testmeil saadeti alati poe viimase tellimuse pealt, mitte selle pealt, mis ülaribal valitud oli. Kujundajas vaatasid üht tellimust, postkasti tuli teine.
* Parandus: WooCommerce'i meiliseadetes olev lisatekst läks täisrežiimis kirja, aga kujundajas seda ei näidatud. Nüüd on see kanvasel näha ja režiimi selgitus ütleb, kust see tuleb ja kuidas selle ära võtta.
* Parandus: vahekaardid ei mahtunud vasakusse tulpa ära ja nimed jäid poolikuks. Nüüd murduvad nad kahele reale.

= 0.17.1 =
* Parandus: plokk „Tellimuse väli (üks rida)" ootas toorest võtit (_tracking_number), aga Muutujad-vahekaardilt kopeerides tuleb kaasa märgendi kuju {{meta:_tracking_number}}. Kaks sarnase väljanägemisega välja ootasid erinevat sisu ja tulemus oli vaikne kriips. Nüüd töötavad mõlemad kujud.
* Välja võtme lahtris on nüüd rippnimekiri selle tellimuse päris väljadega koos väärtusega — ei pea enam võtit peast teadma ega mujalt kopeerima.
* Parandus: kujundaja eelvaates ei olnud „Tellimuse väli" plokil päris linki, ainult lingi välimus. Nüüd on link sama, mis kirja läheb.

= 0.17.0 =
* Märgendeid on nüüd 50 asemel 20: kõik arve- ja tarneaadressi väljad eraldi (linn, sihtnumber, riik, ettevõte, aadressiread), tellimuse ID ja võti, olek, vahesumma, allahindlus, tarne summa, käibemaks, valuuta, maksmise kuupäev, maksmise link, makseviisi tunnus eraldi nimest.
* Uus vahekaart „Muutujad" — kõik märgendid rühmade kaupa koos kirjelduse ja valitud tellimuse tegeliku väärtusega. Otsing ja kopeerimisnupp, et saaks kleepida ka sinna, kuhu { } nupp ei ulatu (nt „Oma HTML" plokk või lisa-CSS).
* { } menüü näitab nüüd samuti rühmi ja valitud tellimuse tegelikke väärtusi, mitte ainult kirjeldusi.

= 0.16.0 =
* Uus plokk „Tellimuse andmed (tabel)" — silt-väärtus read ühes või kahes veerus. Väärtuse valid { } menüüst, kus on nii üldised märgendid kui selle tellimuse päris väljad (nt Montonio jälgimiskood). Igale reale saab lisada lingi, kus {{value}} asendub väärtusega. Tühjaks jäänud read peidetakse.
* Parandus: JavaScripti pool ei osanud märgendit {{meta:võti}} lahendada — see töötas ainult päris kirjas. Kujundajas jäi väärtus tühjaks ja peitmise korral kadus terve rida.
* Parandus: muutujavaliku ({ }) sisestus kirjutas väärtuse ainult ekraanile, mitte andmetesse, kui väli ei olnud tavaline ploki väli. Nüüd teatab väli ise oma muutusest, seega valik jõuab kohale igal pool.

= 0.15.1 =
* Parandus: plokk „Tellimuse väli" näitas kujundajas kriipsu, kui välja võti oli määramata või tellimusel puudus — päris kirjas peitis ta end ära. Nüüd käitub kujundaja täpselt nagu päris kiri.

= 0.15.0 =
* Aadressiplokk on nüüd meie oma, mitte WooCommerce'i mall. Kadusid kaldkiri, hallid raamitud kastid ja allajoonitud telefoninumbrid — aadressid on samas kirjas ja värvides kui ülejäänud kiri.
* Aadressiplokil on seaded: kas näidata mõlemat, ainult arve- või ainult tarneaadressi; pealkirjad ümberkirjutatavad; telefoni ja e-posti saab välja lülitada; raamitud kasti saab soovi korral tagasi.
* „Värskenda serverist" nupp on kadunud. Tellimuse andmed tulevad serverist niikuinii iga kord, kui meili või tellimust vahetad — eraldi nuppu ei olnud vaja.

= 0.14.2 =
* Parandus: kujundaja näitas muutujate asemel alati näidisväärtusi — „Tere Mari" ja „tellimus #1042" ka siis, kui ülariba tellimusevalikus oli päris tellimus. Nüüd saadab server valitud tellimuse päris väärtused ja need kirjutavad näidise üle.
* Parandus: plokid „Aadressid", „Tellimuse tabel (WooCommerce)" ja „Makseviisi juhised (WooCommerce)" näitasid kujundajas alati näidissisu. Nüüd renderdab server need valitud tellimuse pealt ja kanvas näitab päris andmeid.
* Parandus: plokk „Kliendi märkus" näitas kujundajas väljamõeldud märkust. Nüüd tuleb tellimuse päris märkus, ja kui seda pole, käitub plokk sama moodi nagu päris meilis.
* Parandus: plokk „Tellimuse väli" näitas kujundajas alati näidisjälgimiskoodi. Nüüd näitab valitud tellimuse tegelikku väärtust ja peidab end, kui väli on tühi.

= 0.14.1 =
* Parandus: täisrežiimis („terve meil ise") näitasid plokid „Tooted (oma tabel)" ja „Kokkuvõte (oma tabel)" kujundajas näidistooteid, mitte valitud tellimuse päris ridu. Tellimuse andmeid ei küsitud serverist, sest täisrežiimis ei ole WooCommerce'i sisuosa vaja — aga tellimuse read on. Nüüd tuuakse need mõlemas režiimis.

= 0.14.0 =
* Parandus: makseviiside juhised ei jõudnud kunagi serverisse. Tühi PHP massiiv jõuab JavaScripti massiivina, mitte objektina, ja massiivile lisatud võtmed kadusid salvestamisel vaikselt ära. Kõik, mis vahekaardile „Makseviisid" kirjutati, läks kaotsi.
* Parandus: plokkide lohistamine muutis ainult nimekirja välimust, mitte järjekorda. Järjekord salvestati drop-sündmusel, mis käivitub ainult lubatud kukutamiskohas — nimekirja serval või väljaspool lastes jäi see tulemata. Nüüd salvestatakse dragend-sündmusel, mis käivitub alati.
* Uus: iga ploki juures on nooled üles-alla. Lohistamine töötab, aga nooltega on kindel.
* Uus: sotsiaalmeedia plokis on nüüd päris logod (Facebook, Instagram, YouTube, LinkedIn) brändivärvides PNG-piltidena, mitte tähtedega mullid. Ikooni suurus ja vahed on seadistatavad.

= 0.13.0 =
* Uus plokk „Tooted (oma tabel)" — sina valid, millised veerud kirja lähevad (pilt, toode, tootekood, variandid, kogus, ühiku hind, rea summa), millises järjekorras ja mis sildiga. Enam ei pea leppima WooCommerce'i malliga.
* Uus plokk „Kokkuvõte (oma tabel)" — vali, millised read näidatakse (vahesumma, allahindlus, tarne, makseviis, käibemaks, kokku), muuda silte, vali paigutus ja jooned.
* Uus vahekaart „Makseviisid" — iga makseviisi juures on väli, kuhu kirjutada juhised, nt pangaülekande rekvisiidid ja märkus, millal tooted broneeritakse. Kirja toob need plokk „Makseviisi juhised (oma tekst)", mis näitab alati selle tellimuse makseviisi teksti.
* Väljavalik ({ } nupu all) näitab nüüd ka selle tellimuse päris välju koos näidisväärtusega — tarnepluginate jälgimiskoodid jm on nimekirjas leitavad, võtmeid ei pea peast teadma.
* Uued märgendid: kliendi telefon, ettevõte, arve- ja tarneaadress ühes reas, tellimuse olek, toodete arv, kliendi märkus.
* „Kontrolli serverist" nupp on kadunud. Tellimuse valik ülaribal toobki serverist selle tellimuse andmed — WooCommerce'i sisu, tooteread, kokkuvõtte ja väljade nimekirja. Kõrvale jäi nupp „Värskenda serverist", kui tahad andmed uuesti küsida.
* Parandus: veergude nimekiri sattus üldise väljasidumise alla ja kirjutati üle, mistõttu veeru sisse-välja lülitamine ei mõjunud.
* Parandus: uued plokid jagasid vaikeväärtuste massiivi, mistõttu ühe ploki veergude muutmine oleks muutnud ka teisi.

= 0.12.0 =
* Kujundaja kanvas näitab nüüd WooCommerce'i enda sisu päriselt, mitte näidist. Nii on kohe näha, kui su enda plokk ütleb sama, mida WooCommerce'i mall — varem tuli selle avastamiseks eraldi serveri eelvaadet vaadata.
* WooCommerce'i osa on kanvasel katkendjoonega märgistatud ja selle küljes on nupp „Võta üle", mis lülitab meili täisrežiimi ja tõstab olemasolevad plokid kehasse. Wrap-režiimi plokid jäävad alles, nii et tagasi minnes ei ole midagi kadunud.
* „Serveri eelvaade" on ümber nimetatud „Kontrolli serverist" ja jäänud kõrvaliseks kontrollinupuks — tõde on nüüd kanvasel endal.
* WooCommerce'i sisuosa küsitakse serverist ühe korra meili ja tellimuse kohta ning hoitakse mälus, seega kanvas jääb plokkide sättimisel sama kiireks kui varem.

= 0.11.0 =
* Uus: eelvaate tellimuse valik ülaribal. Saad valida, millise päris tellimuse andmetega meili näidatakse — nii saab kontrollida, kuidas kiri eri makseviiside puhul välja näeb. Valik kehtib ka testmeili saatmisel.
* Uus: iga ploki juures saab määrata, milliste makseviiside puhul see kirja läheb. Nii saab nt pangaülekande juhised panna ainult ülekandega tellimustele. Märkimata jätmine tähendab „näita alati".
* Uus plokk „Makseviisi juhised" — toob kirja selle, mida makselahendus ise meilile lisab (nt pangaülekande rekvisiidid). Täisrežiimis oleks see muidu kaduma läinud.
* Serveri eelvaade on nüüd režiim, mitte ühekordne vaade. Meili või tellimuse vahetamisel värskendub see ise, enam ei pea nuppu uuesti vajutama.

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
