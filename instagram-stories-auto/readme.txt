=== Instagram Stories Auto Publisher ===
Contributors: darthberth
Tags: instagram, stories, auto publish, social, featured image
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pubblica automaticamente una Storia su Instagram quando pubblichi o aggiorni un post WordPress, usando l'immagine in evidenza e sovrimprimendo titolo, call-to-action e link all'articolo.

== Descrizione ==

Quando pubblichi un post (e, se lo attivi, ogni volta che lo aggiorni), il plugin:

1. Prende l'**immagine in evidenza** del post.
2. Genera un'immagine verticale 1080×1920 in formato Storia con l'immagine, il **titolo** del post, una **call-to-action** e il **link/handle**.
3. La pubblica come **Storia Instagram** tramite la Instagram Graph API ufficiale.

Il tutto avviene in modo asincrono (via WP-Cron), così il salvataggio del post resta veloce.

= Limite importante sui link =

Instagram **non consente** di aggiungere link-sticker cliccabili tramite API (vale per qualsiasi strumento di terze parti). Per questo il plugin scrive il link/la call-to-action **direttamente sull'immagine** della Storia, come promemoria visivo. Per un link cliccabile reale resta il classico "link in bio".

== Requisiti ==

* Un account Instagram **Business** o **Creator** collegato a una **Pagina Facebook**.
* Una **app** su Meta for Developers con i permessi `instagram_basic` e `instagram_content_publish`.
* Un **Access Token long-lived** e l'**Instagram Business Account ID**.
* L'estensione **GD** di PHP attiva (per generare l'immagine).
* Il sito deve essere **pubblicamente raggiungibile** (Instagram scarica l'immagine da un URL pubblico: non funziona in locale/localhost).

== Installazione ==

1. Copia la cartella `instagram-stories-auto` in `wp-content/plugins/` (oppure caricala come ZIP da Plugin > Aggiungi nuovo).
2. Attiva il plugin.
3. Vai su **Impostazioni > Instagram Stories** e inserisci Instagram Business Account ID e Access Token.
4. Usa il pulsante **Verifica connessione** per controllare le credenziali.
5. Configura call-to-action, handle, colore accento e i comportamenti di default.

Su ogni post trovi il box **"Storia Instagram"** per attivare/disattivare la pubblicazione e la ripubblicazione agli aggiornamenti, e per vedere l'ultimo esito.

== Come ottenere le credenziali (sintesi) ==

1. Crea un'app su https://developers.facebook.com/ (tipo "Business").
2. Aggiungi il prodotto **Instagram Graph API**.
3. Collega la Pagina Facebook e l'account Instagram Business.
4. Genera un **User Access Token** con i permessi `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`.
5. Scambialo per un **token long-lived** (60 giorni) e recupera l'**IG User ID** con `GET /me/accounts` → `instagram_business_account`.

== Filtri per sviluppatori ==

* `isa_should_publish_story` — `apply_filters( 'isa_should_publish_story', $publish, $post_id, $intent )` per annullare a runtime.

== Changelog ==

= 1.0.0 =
* Prima versione: pubblicazione automatica della Storia su publish e (opzionale) su update, con immagine in evidenza + overlay di titolo, CTA e link.
