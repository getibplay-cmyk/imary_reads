# Installation et déploiement

Imary Reads utilise Laravel 13 / PHP 8.3+, PostgreSQL en production et SQLite pour le développement. L’arabe est la langue d’interface par défaut, avec français et anglais. Le sens de lecture dépend de la langue du livre.

## Développement

```bash
composer setup
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Ouvrez `http://localhost:8000`. Lancez séparément `php artisan queue:work --tries=3 --timeout=120` et `php artisan schedule:work`. Les e-mails sont écrits dans les journaux tant que SMTP n’est pas configuré. Le seeder de démonstration contient des textes originaux illustratifs, sans faux avis ni statistiques; il est interdit en production.

Pour créer le premier administrateur : `php artisan imary:admin votre@email.example`. Le mot de passe est saisi dans une invite masquée. Activez ensuite la double authentification. Aucun compte administrateur prédéfini n’est livré.

## Production

Copiez `.env.production.example` vers `.env`, puis renseignez le domaine, APP_URL, un APP_KEY unique, le mot de passe PostgreSQL, SMTP et l’adresse de contact. Conservez les secrets hors Git et sauvegardez APP_KEY : il est nécessaire pour déchiffrer les données existantes.

```bash
docker compose build --pull
docker compose up -d postgres signatures
docker compose run --rm app php artisan migrate --seed --force
docker compose run --rm app php artisan imary:admin votre@email.example
docker compose up -d
docker compose exec app php artisan imary:doctor --production --scan
```

Caddy sert le dossier public et HTTPS. PostgreSQL et PHP-FPM ne sont pas exposés sur Internet. Les workers analysent les fichiers avec ClamAV; le service signatures met à jour ses bases. Un échec d’analyse maintient le fichier en quarantaine. N’utilisez jamais un faux scanner en production.

Les volumes contiennent des données réelles : **ne lancez pas `docker compose down -v`**. Prévoyez des sauvegardes chiffrées de la base, des fichiers privés et des secrets, et testez une restauration isolée. Consultez [le guide détaillé](DEPLOYMENT.md) pour les mises à jour et l’exploitation.

## Périmètre et vérification

PDF est une pièce jointe téléchargeable après analyse, sans lecteur PDF ni OCR. EPUB/TXT peuvent être importés en chapitres; les mises en page complexes et médias externes ne sont pas conservés. Le lecteur livre utilise du vrai texte et une pagination adaptative, pas une image factice.

Les tests PHP/SQLite et JavaScript ainsi que les compilations ont été exécutés. Les conteneurs, PostgreSQL réel, SMTP externe, une analyse positive avec ClamAV installé et l’interface dans un navigateur restent à valider sur l’environnement cible. Le workflow CI prévu les complète partiellement une fois le dépôt accessible en écriture.

La tentative d’écriture GitHub a reçu une erreur 403 du connecteur. Aucun déploiement public n’a été annoncé ou effectué. Le code et la procédure sont livrés; le passage en production nécessite la configuration et la vérification du serveur réel.
