# Artwork, content and dependency provenance

The user-provided multi-panel mockup guided palette, layout, Moroccan architecture, author studio and book-reader styling. It is not used as a flattened screenshot in the application.

Four original illustrations were generated for this project on 2026-10-21: a lantern-lit Moroccan riad at night (`hero`), an Andalusian courtyard (`andalus`), a desert journey (`desert`), and a fantasy city (`fantasy`). They contain no textual cover title; titles are rendered as accessible HTML. Original PNG files are retained in `resources/artwork`; `scripts/build-artwork.php` encodes WebP derivatives for delivery. The logo is project-authored SVG. There are no external image hotlinks.

The optional demo stories and multilingual welcome article were written for this project. They are clearly illustrative opening chapters, not copies of published novels or claims about real authors. Production seeds only taxonomy; operators and authors supply works they have rights to publish.

Fonts: Tajawal, Noto Naskh Arabic and Inter are distributed through the installed @fontsource packages under their respective licenses. Font files are served from the same origin. Retain their package license notices in distributions that bundle dependencies.

Runtime dependencies include Laravel/Fortify (MIT), Symfony HTML Sanitizer (MIT), Tiptap open-source packages (MIT), and StPageFlip (MIT). Exact versions are locked in `composer.lock` and `package-lock.json`; installed dependency distributions contain their licenses. The application source is owned/controlled by its owner and does not grant rights to uploaded third-party works.

Primary technical references consulted:

- https://laravel.com/framework/docs/13.x/releases
- https://laravel.com/framework/docs/13.x/localization
- https://laravel.com/framework/docs/13.x/http-tests
- https://nodlik.github.io/StPageFlip/
- https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- https://caddyserver.com/docs/caddyfile/directives/php_fastcgi
- https://docs.clamav.net/manual/Usage/Scanning.html
- https://docs.docker.com/compose/how-tos/startup-order/
