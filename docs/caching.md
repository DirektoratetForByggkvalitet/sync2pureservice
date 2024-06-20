# Mellomlagring (cache)

Sync2pureservice bruker [Laravel sine funksjoner](https://laravel.com/docs/11.x/cache) for å mellomlagre data, typisk for å spare PHP sitt minneforbruk og for å kunne gjøre oppslag på de samme dataene raskere.

Man bruker miljøvariabelen CACHE_DRIVER for å velge teknologi for mellomlagring. Som standard vil sync2pureservice bruke 'file'. En rekke andre teknologier er tilgjengelige, noen av dem trenger en del konfigurasjon:

| CACHE_DRIVER | Kommentar |
|----|----|
| file | Mellomlagring gjøres i form av filer i storage/cache. Litt treg, men grei. Standardverdi dersom CACHE_DRIVER ikke finnes |
| apc | Mellomlagring gjøres i minne, med PHP-tillegget [APCu](https://www.php.net/manual/en/book.apcu.php). Rask mellomlagring, men belaster også minnet til maskinen |
| redis | Mellomlagring gjøres i Redis Cache, en veldig rask database-tjener. sync2pureservice er satt opp til å bruke predis/predis-pakken, en PHP-basert Redis-klient som ikke krever et ekstra PHP-tillegg. Likevel er det lurt å ha PHP-tillegget igbinary installert, samt at lz4 (eller liblz4) er installert på maskinen som skal kjøre sync2pureservice eller som PHP-tillegg. Det kan tenkes at du også må endre i [config/database.php](config/database.php) for at det skal fungere |
| memcached | Mellomlagring gjøres i Memcached, en minnebasert databasetjener. Dette krever at PHP-tillegget memcached er installert, og at oppsett settes opp i [config/database.php](config/database.php) |

Det finnes også andre muligheter her. Memcached og Redis blir sett på som de raskeste, og har også en fordel i at mellomlagringen kan deles av flere runners.

Blir dette for komplisert? Ikke bruk CACHE_DRIVER. Det vil fungere helt greit.
