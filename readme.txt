=== Wonom Email Designer ===
Contributors: wonomdigital
Tags: woocommerce, email, template, editor, transactional
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visual designer for WooCommerce order emails. Set the brand once and it applies to every email.

== Description ==

Wonom Email Designer gives WooCommerce transactional emails your brand look, without copying templates or writing code.

**What sets it apart from other email builders:** you do not build fourteen emails one by one. You set the brand once — logo, colours, type, button style, header and footer — and it applies to every email automatically. Only when one particular email needs something extra (a greeting, a button, delivery info) do you add blocks to it.

= What you can do =

* **Brand** — logo, colour palette, font and size, button style, email width, corner radius, inner padding, extra CSS.
* **Header and footer** — shared by every email, built from blocks.
* **Per-email content** — its own subject line, large heading, and blocks before or after the order table.
* **Blocks** — heading, paragraph, button, image, divider, spacer, two columns, images side by side, social media, custom HTML, and WooCommerce's own order table, addresses and totals.
* **Variables** — `{{customer_first_name}}`, `{{order_number}}`, `{{order_total}}`, `{{order_url}}` and more, from a picker that shows the real values of the selected order.
* **Live preview** — every change is on screen at once, in desktop and mobile view.
* **Test email** — sends the real WooCommerce email, but to a test address.
* **Export and import** — the whole design in one file, to move it to another shop.
* **Switches** — each email has its own: switched off, that email goes out exactly as WooCommerce sends it, and everything you built stays saved. The main switch under Settings turns the whole takeover off at once and the WooCommerce default templates come back.

= Languages =

The interface is in English and Estonian. The source language is English; Estonian comes from the bundled translation. Another language needs only a `.po` file — the `.pot` template is in `languages/`.

The emails themselves follow the customer. In a multilingual shop (WPML / WooCommerce Multilingual or Polylang) each language can have its own subject lines, headings, blocks and payment instructions; anything you leave untranslated comes from the default language.

= Emails covered =

Order processing, completed, on hold, refunded, invoice / pending payment, note to customer, password reset, new account, new order to the shop, cancelled order, failed order.

= How it works =

The plugin replaces the WooCommerce templates `emails/email-header.php` and `emails/email-footer.php` and adds the brand CSS through the `woocommerce_email_styles` filter. The order table and the addresses are still rendered by WooCommerce itself — so nothing is lost when WooCommerce updates those templates.

If your theme has overridden those templates, the plugin leaves the theme's version alone.

== Installation ==

1. Upload the folder `wonom-meilidisainer` to `/wp-content/plugins/`.
2. Activate the plugin on the WordPress plugins page.
3. Open **WooCommerce → Wonom Email Designer**.
4. Build the design, or import one from another shop under Settings.
5. Switch it on under **Settings → Design in use**.

On a fresh install the design is switched off, so activating the plugin does not change a single customer email. You decide when it goes live.

== Frequently Asked Questions ==

= I activated the plugin and the emails look the same. Why? =

On a fresh install the design is switched off on purpose, so that activating the plugin on a live shop cannot change customer emails before you are ready. Switch it on under **Settings → Design in use**. The designer also says so in a strip above the preview.

= Do my existing WooCommerce email settings stay? =

Yes. Enabling and disabling emails, recipients and the default subjects stay in the WooCommerce settings. The Email Designer overrides the subject only when you have filled that field in.

= Does the plugin change the order table? =

No, WooCommerce renders the table. The Email Designer only gives it the brand colours and type.

= What happens if I switch the plugin off? =

Emails go back to the WooCommerce default design. Your design stays in the database.

== Changelog ==

Entries up to 0.23.0 are in Estonian — that was the source language until 0.24.0.

= 1.0.10 =
* **Fix: the contacts were added even when the parcel went to the same person.** The check compared the whole address, and a parcel locker makes the address text different even when the recipient is not. It now compares the name and the phone number, which is what actually says whether somebody else is receiving the parcel. A country code in front of one of the numbers does not count as a difference.
* **The shipping address block can now be hidden when the recipient is the same person.** A new setting in the Addresses block: always, or only when the recipient differs. Useful when the parcel machine is already named on the delivery row of the summary and the shipping address would just repeat the buyer's own name.

= 1.0.9 =
* **Phone and email at the shipping address, for when the parcel goes to somebody else.** The Addresses block has a new setting with three states: do not show, show always, or — the useful one — show only when the recipient differs from the person paying. Same person, same address: nothing is added, because those details are already at the billing address.
* The recipient's own phone is used when the checkout collected one. WooCommerce does not collect a separate email for the recipient, so that falls back to the address on the order — the one the shop can actually reach about the parcel.

= 1.0.8 =
* **Fix: "Coupon code" went into the email but was missing from the block's row list.** A saved design only knows the rows that existed when it was saved, so the new row could not be switched off or moved — it just turned up above the grand total. The list now always shows every row the plugin offers.
* A row the plugin adds later lands where it belongs — "Coupon code" appears right under "Discount", not at the bottom of the list — and it arrives switched off, so no email changes until you tick it.
* Fix: the row, pair and image-card editors wrote into the default language when you were editing a second one. The same leak as 1.0.4, in the three editors that bypassed the usual path.

= 1.0.7 =
* **A "Coupon code" row in the "Totals (own table)" block.** Tick it on and the code used on the order appears in the summary, next to the discount amount. WooCommerce does not offer this row itself — its summary only has the amount — so the plugin builds it from the order's coupons. When no code was used the row is not there at all.
* **Fix: the up/down arrows in that block did nothing to the email.** The hint said you could reorder the rows, but the order came from WooCommerce's own data and the arrows only moved things around on screen. The rows now go out in the order you set them. Rows that are not in the list (service fees, extra tax lines) stay where they were, and the grand total stays last.

= 1.0.6 =
* **New variable: `{{coupon_codes}}`** — the coupon codes used on the order, in capitals and comma-separated when there is more than one. It appears in the Variables tab and in every { } picker, under Order.
* When no coupon was used the variable is empty, so a row in the "Order details (table)" block with "hide empty rows" switched on simply does not appear. That gives you "was a code used, and which one" without an empty line on every other order.

= 1.0.5 =
* **Fix: the variable picker was unusable on the "Custom HTML" and paragraph blocks.** Pressing { } gave a narrow sliver of a menu on top of the other settings instead of the list of variables. The menu was anchored to the right edge of a button sitting at the left edge of the toolbar, so it ran off the panel and the panel's scrolling cut it down to a strip. It now spans the field and stays inside the panel.
* Fix: the variables in that menu were rendered as the toolbar's own little bordered buttons, squeezed together and hard to read. The toolbar styling no longer reaches into the menu.

= 1.0.4 =
* **Fix: editing a block while viewing a second language changed the default language too.** Text typed in the English view appeared in the Estonian email, and the other way round. Until a part has been translated, the second language shows the default language's blocks — and the designer was letting you edit those very blocks rather than a copy. The block list on the left was already protected; clicking a block in the preview was not. Now the first edit gives that language its own copy, exactly as the "Translate this part" button does.
* The same applies to adding, moving, duplicating, deleting and dragging blocks: they stay in the language you are editing.

= 1.0.3 =
* **Fix: "WooCommerce additional content at the end" did nothing in the preview when the design was switched off on that email.** The text stayed on the canvas however the switch was set. Same cause as the 1.0.2 fix, in a second place: the preview asks WooCommerce for that text and the switches were being applied to the answer. The canvas shows the design, so it now shows the design's decision.
* Fix: in "Build the whole email" mode the test email left out the WooCommerce additional content, while the real email included it. The two now match.

= 1.0.2 =
* **Fix: the preview showed sample content whenever the design was switched off.** "The WooCommerce content could not be rendered" — because with the design off the plugin's own header and footer are not used, and those are what mark where the WooCommerce part starts and ends. Since 1.0.0 a fresh install starts switched off, so this met you on the very first screen. The preview now always builds with the design, whatever the switches say; they govern real emails, not the canvas.
* The test email sent from the designer now also shows the design while the switches are off. Otherwise the designer's own button put the plain WooCommerce email in your inbox and the plugin looked broken. It still does not reach a single customer — the strip above the preview says so.

= 1.0.1 =
* **Fix: the desktop/mobile switch disappeared in a narrow window.** The dropdowns in the top bar refused to shrink, so they pushed the switch out of sight behind the buttons. The bar now scales with the window: the dropdowns narrow first, and if there is still no room, the row wraps. Nothing is left hidden.

= 1.0.0 =

First stable release. Everything from 0.23.0 to 0.31.0 is in, and the plugin is ready for a live shop.

* **A fresh install no longer changes anything by itself.** Activating the plugin used to switch the design on at once, which on a live shop meant the next customer email went out with the default look — no logo yet, no colours. Now a fresh install starts switched off and you turn it on under Settings → Design in use when the design is ready. Shops that already have a design are not touched.
* Uninstalling now also removes the DeepL key and the translation settings. They used to stay behind in the database.

= 0.31.0 =
* **Fix: "Install now" in the designer ended in a timeout (HTTP 524).** The update was found, but installing it never finished. Before starting, the plugin forced WordPress to re-check every plugin on the site — which sets every other plugin's own updater going and sends the whole plugin list to wordpress.org. On a shop with many plugins that took longer than the server allows a single request to run. The updater is now handed only this plugin's entry, and only while it reads it: nothing else is set going.
* Fix: downloading the package could hang for up to five minutes. WordPress's default is 300 seconds, longer than most servers allow, so instead of an error message you got a timeout page. The download now gives up after 45 seconds and says what went wrong.

= 0.30.0 =
* **TikTok in the social media block.** Its own link field and the real TikTok icon, next to Facebook, Instagram, YouTube and LinkedIn. Leave the field empty and the icon does not appear.
* The menu entry under WooCommerce now reads "Wonom Email Designer", so it is clear whose plugin it is when a shop has several.

= 0.29.0 =
* **One switch instead of two.** After 0.28.0 there were two identical-looking switches on screen — the main one in the top bar and the per-email one in the panel. The main switch has moved to Settings, where it belongs: it governs the whole plugin, not the email you happen to be looking at. The top bar now has actions only.
* **A warning strip above the preview when the design does not reach the customer** — either because the main switch is off or because it is off on this email. Neither switch is on screen all the time any more, so without this you could spend an hour designing an email that never goes out.

= 0.28.0 =
* **The design can now be switched on and off one email at a time.** Until now it was all emails or none. Each email has its own switch in the designer; switching it off sends that email exactly as WooCommerce sends it, and everything you built stays saved and comes back when you switch it on again. The main switch under Settings still governs the lot.
* **The designer now has a column on the WooCommerce email settings page.** WooCommerce → Settings → Emails shows for each email whether the design is on and gives you a button straight into the designer, on that email. That is where a shop is managed from, so that is where the way in belongs.
* The "Emails" tab is now called "Email content" — the tab is about what goes inside the email, and the old name suggested it was a list of emails.

= 0.27.0 =
* **Fix: the test email always went out in the order's language.** If you were looking at English and pressed "Send test email", you got the Estonian email, because the real WooCommerce email always follows the order. Now the language you have picked in the designer wins, and when it differs from the order's the email is put together by the designer instead.
* Fix: the test email's subject line was the name of the email type, not the subject. Now it comes from the same place as in the real email — from the design if you have filled it in, otherwise from the WooCommerce settings.
* **Fix: the preview showed WooCommerce's own strings in the wrong language.** Things like "includes X tax" are built as the email is rendered and follow the WordPress locale, not the WPML language. The preview only switched the language, so it showed Estonian where the real email would be English — the preview was lying about the result. It now switches the locale as well.
* Automatic translation now also fills the subject line and the large heading when they are empty in the default language. In that case the text comes from the WooCommerce settings, and until now it was simply left untranslated.

= 0.26.0 =
* The designer now says which language you are editing and what follows from it — before this the change was easy to miss, because an untranslated language looks exactly like the default one. That is correct behaviour, but it gave you no way to tell whether switching had done anything at all.
* **Automatic translation.** One button fills a language from the default one, and you correct it instead of writing everything twice. Set a DeepL key under Settings; the free tier gives 500,000 characters a month, far more than email templates need.
* DeepL was chosen because it is the only service that leaves `{{variables}}` untouched. Without that the machine would translate `{{customer_first_name}}` as well and the emails would break. Links, colours and the "Custom HTML" block are left alone too.
* Which fields get translated is declared in the block schema itself, so a new block only has to say so — nothing needs changing in the translation code.
* Fix: arrays sent to the server were flattened by the browser into one comma-separated string, so only the first text would have come back translated.

= 0.25.0 =
* Emails now go out in the customer's language. In a multilingual shop (WPML / WooCommerce Multilingual or Polylang) the designer gets a language picker, and each language can have its own subject lines, headings, blocks and payment instructions.
* The default language's content stays where it has always been, so nothing needs migrating and a single-language shop sees no change at all — the language picker only appears when the shop has more than one language.
* A part that has not been translated yet shows the default language's blocks, greyed out, with a "Translate this part" button. Pressing it copies them into that language, where you can edit them freely. Until then that language simply uses the default content, so no email ever goes out empty.
* The brand — logo, colours, type, layout — is shared by every language. So is how an email is put together, since that is structure rather than content.
* Untranslated payment instructions fall back to the default language one gateway at a time, so a customer never gets an email with the payment details missing.
* The order's own language is used, taken from the order itself, not from whichever language the site happened to be in when the email was sent.

= 0.24.0 =
* The plugin is now bilingual. The source language is English and Estonian comes from a bundled translation, so the designer follows the language of the WordPress user: English admin, English interface; Estonian admin, Estonian interface.
* All 407 texts go through the standard WordPress translation system — 236 in PHP, 193 in JavaScript. Another language needs only a `.po` file; the `.pot` template is in `languages/`.
* The JavaScript texts used to travel through a `wp_localize_script` array and were partly hardcoded. They now use `wp.i18n` like the rest of WordPress, so there is one way to translate instead of two.
* Nothing changed in how emails are put together, and no saved design is affected. The text you have written into blocks yourself stays exactly as it is — that is content, not interface, and it is not translated.

= 0.23.0 =
* **Oluline parandus:** „Võta üle" jättis kirja sisse selle tellimuse päris andmed — kliendi nime, tellimuse numbri ja kuupäeva. Nii oleks iga järgmine kiri läinud kõigile klientidele ühe konkreetse tellimuse nimega. Nüüd võetakse need ülevõtmisel tagasi muutujateks ({{customer_name}}, #{{order_number}}, {{order_date}}).
* Uus muutujarühm „Konto": kasutajanimi, kasutaja e-post, kuvatav nimi, parooli seadmise ja lähtestamise link, sisselogimise link. Ilma nendeta ei saanud „Parooli lähtestamise" kirja täisrežiimis üldse kasutada — lähtestuslinki polnud kuhugi panna.
* Kontomeilides töötavad nüüd ka {{customer_first_name}} ja {{customer_name}} — varem näitas see e-posti aadressi või jäi tühjaks.
* Parandus: testmeil ja serveri eelvaade näitasid kontomeilide puhul näidissisu tellimuse tabeliga. Nüüd tuleb sisu WooCommerce'ilt, nagu kanvasel.
* Parandus: „Võta üle" kaotas pildid. Pilt tekstiplokis ei elanud puhastust üle; nüüd tehakse sellest pildiplokk.
* Parandus: muutujat sisaldav aadress (nt jälgimislink https://…/{{meta:_kood}}) rikuti salvestamisel ära. Nüüd jääb alles.
* Parandus: enne mõne välja lisamist salvestatud plokk võis anda PHP hoiatuse, mis jõudis kirja sisse. Plokk saab nüüd puuduvad väljad vaikeväärtustena.
* Parandus: tellimuste nimekiri kujundaja ülaribal kasutas kõvakodeeritud kuupäevaformaati; nüüd poe enda oma.
* Parandus: kategooriapilti ei lõigatud serveris, sest pisipildi aadressi järgi ei leitud manust. „Püstine" ja „lamav" kuju töötavad nüüd ka Outlookis.
* Turvalisus: „Oma HTML" ja lisa-CSS lasevad nüüd poehaldajal läbi ainult tavalise postituse HTML-i; administraatoril nagu enne.
* Turvalisus: GitHubi juurdepääsuvõti saadetakse ainult sinu enda hoidla päringutega, mitte igale api.github.com aadressile.

= 0.22.0 =
* Plokil „Pildid kõrvuti" on nüüd seade „Pildi kuju": ruut, püstine, lamav või originaal. Meediateegis on pildid eri kõrgusega ja rida jäi seetõttu ragiseks — nüüd lõigatakse nad keskelt ühesuuruseks.
* Meediateegi pildid lõigatakse serveris päriselt valmis, mitte ainult CSS-iga. Nii on nad ühesugused ka Outlooki töölauaversioonis, mis object-fit'i ei tunne. Lõigatud fail tehakse ühe korra ja jääb meediateeki alles.
* Tootekategooriate pildid võetakse WooCommerce'i enda pisipildi mõõdust, mis on juba ühesuuruseks lõigatud.
* Parandus: ääremiste piltide lahtritel oli vähem polstrit kui keskmistel, mistõttu need jäid paar pikslit laiemaks. Nüüd on polster ühesugune ja tabeli laiused fikseeritud.

= 0.21.0 =
* Uus plokk „Pildid kõrvuti": 2, 3 või 4 pilti ühes reas, iga pildi taga link ja all nimi. Sobib tootekategooriate ribaks kirja lõppu.
* Ploki saab täita ühe klõpsuga tootekategooriatest — nimi, aadress ja kategooria pilt tulevad WooCommerce'ist. Nimekiri jääb lahti, nii et neli pilti saab järjest valida.
* Telefonis murrab neli pilti kaks kaupa, et need liiga kitsaks ei jääks. Outlookis jäävad nad kõrvuti.

= 0.20.0 =
* Parandus: salvestus katkes veateatega „SyntaxError … is not valid JSON", kui mõnes meilis oli „Oma HTML" plokk <script> või <style> märgendiga. Serveri tulemüür blokeeris sellise päringu enne WordPressi jõudmist. Kujundus saadetakse nüüd base64-kujul, nii et tulemüür ei näe seda ründena. Sisu ise puhastatakse endiselt serveris.
* Parandus: kui server vastab midagi muud kui JSON-i, ütleb kujundaja nüüd otse, mis juhtus (nt „Serveri tulemüür blokeeris päringu"), mitte ei näita JSON-i parsimisviga.
* Parandus: „Võta üle" ei teinud midagi, kui meilis oli juba plokke — nii jäid lingid ja WooCommerce'i tekst tulemata. Nüüd küsib nupp kinnitust ja toob WooCommerce'i sisu serverist just sel hetkel, koos kõigi linkidega.
* Kontomeilidel (uus konto, parooli lähtestamine) ei ole enam tellimuse valikut. Need kirjad ei käi tellimuse pealt, seega ei saa tellimuse vahetamine nende sisu enam muuta ja tellimuse muutujad on tühjad, mitte näidistellimuse omad.

= 0.19.1 =
* Parandus: režiimi vahetamine ei värskendanud eelvaate vahemälu, mistõttu wrap-režiimi tagasi minnes jäi kanvasele näidissisu. Vahemälu võti sisaldab nüüd kõike, mis serveri vastust mõjutab — režiimi ja lisateksti lülitit —, seega seda viga ei saa enam tekkida.
* Uus: täisrežiimis on nupp „Lae WooCommerce'i sisu plokkidena". Kui meil on juba käsitsi kokku pandud, saab sellega WooCommerce'i praeguse sisu uuesti plokkideks võtta.

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
