# Imary Reads

Plateforme de lecture et de publication de romans développée avec **Laravel 13**. Elle propose une bibliothèque, une recherche instantanée, un espace auteur, des commentaires, des téléchargements autorisés par l’auteur et un lecteur avec affichage en livre.

L’interface est disponible en **arabe, français et anglais**. L’arabe est la langue par défaut ; la langue de l’interface peut être changée depuis le sélecteur du site.

Ce guide explique l’installation **locale sous Windows avec PowerShell**, puis la création des comptes lecteur, auteur et administrateur. Il peut être placé à la racine du projet, à côté de `artisan` et de `composer.json`.

## Sommaire

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Démarrage de la plateforme](#démarrage-de-la-plateforme)
- [Créer un compte lecteur ou auteur](#créer-un-compte-lecteur-ou-auteur)
- [Confirmer un compte local sans envoi d’e-mail](#confirmer-un-compte-local-sans-envoi-de-mail)
- [Créer le premier administrateur](#créer-le-premier-administrateur)
- [Premier essai de publication](#premier-essai-de-publication)
- [Dépannage](#dépannage)
- [Commandes utiles](#commandes-utiles)
- [Documentation](#documentation)

## Prérequis

| Outil | Version ou usage |
| --- | --- |
| PHP | 8.3 ou version ultérieure compatible avec les dépendances verrouillées |
| Composer | Version 2 |
| Node.js | 22.12 ou version ultérieure compatible ; une version LTS convient |
| npm | Fourni avec Node.js |
| SQLite | Base locale utilisée par défaut, via l’extension PHP `pdo_sqlite` |
| VS Code | Éditeur et terminal pour travailler sur le projet |

PHP doit notamment disposer des extensions `mbstring`, `intl`, `dom`, `xml`, `curl`, `fileinfo`, `gd`, `zip`, `PDO` et `pdo_sqlite`. L’extension `pdo_pgsql` est nécessaire si vous choisissez PostgreSQL.

Dans PowerShell, vérifiez les outils :

```powershell
php -v
composer --version
node --version
npm.cmd --version
php -m
```

Si PHP et Composer fonctionnent déjà avec **Laravel Herd**, conservez cette installation. Pour installer les outils manquants, utilisez les sites officiels de [Composer](https://getcomposer.org/doc/00-intro.md#installation-windows) et de [Node.js](https://nodejs.org/en/download). Rouvrez le terminal après leur installation.

Une connexion Internet est nécessaire pour télécharger les dépendances lors du premier lancement.

## Installation

### 1. Extraire le projet et ouvrir le bon dossier

Décompressez entièrement `imary_reads.zip`, puis ouvrez le dossier du projet dans VS Code. Le dossier à utiliser contient les fichiers **`artisan`**, **`composer.json`** et **`package.json`**.

Dans **Terminal > Nouveau terminal**, choisissez PowerShell. Adaptez le chemin si nécessaire :

```powershell
cd "C:\Users\pc\Desktop\imary_reads"
```

Le double dossier `\imary_reads` dépend de l’endroit où vous avez extrait l’archive. C’est la présence de `artisan` qui permet de reconnaître le bon dossier.

### 2. Installer et préparer l’application

```powershell
$env:COMPOSER_PROCESS_TIMEOUT = "1800"
composer setup
```

La première ligne accorde jusqu’à **30 minutes** aux processus lancés par Composer dans cette fenêtre. Elle évite l’arrêt de l’installation à la limite habituelle de 300 secondes.

La commande `composer setup`, définie dans le projet, exécute les étapes suivantes :

1. Installer les dépendances PHP dans `vendor` à partir de `composer.lock`.
2. Créer `.env` depuis `.env.example` s’il n’existe pas.
3. Générer la clé de l’application si elle est absente.
4. Créer la base SQLite locale si elle est absente, appliquer les migrations et ajouter les catégories initiales.
5. Installer les dépendances JavaScript à partir de `package-lock.json`.
6. Compiler les fichiers de l’interface avec Vite.

Attendez la fin de **toutes** les étapes. Le téléchargement des bibliothèques à 100 % ne signifie pas encore que l’installation complète est terminée.

Ce parcours correspond à une installation locale neuve avec les paramètres fournis. Si un fichier `.env` existe déjà, il est conservé et les migrations utilisent la base qu’il désigne.

### 3. Vérifier la configuration locale

Avec les paramètres par défaut, le fichier `.env` contient notamment :

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
DB_CONNECTION=sqlite
MAIL_MAILER=log
QUEUE_CONNECTION=database
```

La clé `APP_KEY`, générée à l’étape précédente, doit rester en place. La base locale se trouve dans `database/database.sqlite`.

Après une modification de `.env`, rechargez la configuration :

```powershell
php artisan config:clear
```

### 4. Ajouter le contenu de démonstration — facultatif

```powershell
php artisan db:seed --class=DemoSeeder
```

Cette commande ajoute six œuvres de démonstration avec de courts textes et un article pour essayer l’interface. Elle ne crée **aucun compte de connexion**. Le contenu sert aux essais locaux et ne constitue pas une bibliothèque de romans complets.

## Démarrage de la plateforme

### Terminal 1 — serveur web

```powershell
php artisan serve
```

Ouvrez [http://localhost:8000](http://localhost:8000). Laissez ce terminal ouvert pendant l’utilisation du site.

### Terminal 2 — traitements en arrière-plan

Ouvrez un autre terminal dans le même dossier :

```powershell
php artisan queue:work --tries=3 --timeout=120
```

### Terminal 3 — tâches planifiées

Dans un troisième terminal, toujours à la racine du projet :

```powershell
php artisan schedule:work
```

Le worker et le planificateur assurent les traitements différés et les tâches périodiques de la plateforme. Pour arrêter un processus dans son terminal, utilisez **Ctrl+C**.

Lors des prochaines utilisations, relancez ces trois commandes. Il n’est pas nécessaire de refaire l’installation ni d’ajouter à nouveau les données de démonstration.

| Page | Adresse locale |
| --- | --- |
| Accueil | [http://localhost:8000](http://localhost:8000) |
| Inscription | [http://localhost:8000/register](http://localhost:8000/register) |
| Connexion | [http://localhost:8000/login](http://localhost:8000/login) |
| Bibliothèque | [http://localhost:8000/library](http://localhost:8000/library) |
| Présentation de l’espace auteur | [http://localhost:8000/writers](http://localhost:8000/writers) |
| Tableau de bord auteur | [http://localhost:8000/writer](http://localhost:8000/writer) |
| Administration | [http://localhost:8000/admin](http://localhost:8000/admin) |
| Sécurité du compte | [http://localhost:8000/security](http://localhost:8000/security) |

## Créer un compte lecteur ou auteur

### Compte lecteur

1. Ouvrez la page `/register`.
2. Renseignez le nom, l’adresse e-mail, le mot de passe et sa confirmation.
3. Laissez l’option de compte auteur décochée.
4. Acceptez les conditions et validez l’inscription.

Le mot de passe doit comporter **au moins 12 caractères**, avec des lettres majuscules, des lettres minuscules et des chiffres. La vérification de l’adresse e-mail est nécessaire pour les fonctions telles que les commentaires et les favoris.

### Compte auteur

Ouvrez directement [http://localhost:8000/register?writer=1](http://localhost:8000/register?writer=1), puis remplissez le formulaire en conservant l’option de compte auteur cochée.

Après confirmation du compte, ouvrez `/writer` pour accéder à l’espace auteur.

Pour devenir auteur avec un compte lecteur existant, connectez-vous, confirmez votre adresse e-mail, puis ouvrez `/writers` et cliquez sur **Devenir auteur**. Le projet crée ainsi le profil d’auteur lié au compte.

## Confirmer un compte local sans envoi d’e-mail

Par défaut, `MAIL_MAILER=log` écrit les e-mails dans le journal de l’application au lieu de les envoyer. Si vous n’avez pas encore configuré l’envoi, vous pouvez confirmer **votre compte d’essai local** depuis le terminal.

Ouvrez un terminal supplémentaire à la racine du projet :

```powershell
php artisan tinker
```

Dans l’invite Tinker, exécutez la ligne suivante en remplaçant `auteur@example.com` par l’adresse exacte du compte déjà créé :

```php
App\Models\User::where('email', 'auteur@example.com')->firstOrFail()->markEmailAsVerified();
```

Le résultat `true` indique que la date de vérification a été enregistrée dans `users.email_verified_at`. Cette opération concerne uniquement le compte sélectionné et n’envoie pas d’e-mail.

Quittez Tinker :

```php
exit
```

Actualisez ensuite la page du site. Si Tinker affiche `No query results for model`, vérifiez l’adresse saisie et assurez-vous que l’inscription a bien été effectuée.

Cette méthode est destinée aux essais locaux. Pour l’utilisation réelle, configurez le service de messagerie et utilisez le lien de confirmation envoyé à l’utilisateur.

## Créer le premier administrateur

Le projet ne fournit **aucun identifiant ni mot de passe administrateur par défaut**.

Dans un terminal normal, hors de Tinker, exécutez :

```powershell
php artisan imary:admin admin@example.com
```

Remplacez `admin@example.com` par l’adresse souhaitée. Elle doit être différente des adresses déjà enregistrées sur la plateforme.

L’assistant demande :

| Invite | Valeur à saisir |
| --- | --- |
| `Display name` | Le nom de l’administrateur |
| `Password (12+ characters, upper/lowercase and numbers)` | Un mot de passe d’au moins 12 caractères, avec majuscules, minuscules et chiffres |

La saisie du mot de passe est masquée : l’absence de caractères affichés est normale.

Après le message `Administrator created` :

1. Déconnectez-vous du compte lecteur ou auteur éventuellement ouvert.
2. Connectez-vous sur `/login` avec le nouvel e-mail et le mot de passe choisi.
3. Ouvrez `/admin`.
4. Si une confirmation du mot de passe est demandée, saisissez le mot de passe de ce compte.

L’adresse e-mail du premier administrateur est automatiquement marquée comme vérifiée. Aucun envoi d’e-mail n’est nécessaire pour cette création.

La commande `imary:admin` fonctionne uniquement lorsqu’aucun administrateur n’existe encore. Les administrateurs supplémentaires se gèrent depuis l’interface d’administration. En environnement de production, l’accès à cette interface exige aussi la double authentification, à activer dans `/security`.

## Premier essai de publication

1. Connectez-vous avec un compte auteur confirmé et ouvrez `/writer`.
2. Créez une œuvre et renseignez ses informations, sa langue, sa catégorie et ses droits de publication.
3. Ajoutez un chapitre, rédigez son texte et enregistrez-le. Pour le rendre lisible publiquement, choisissez le statut de publication approprié et une date déjà atteinte si une date est définie.
4. Soumettez l’œuvre à la validation depuis sa fiche.
5. Connectez-vous avec l’administrateur et approuvez l’œuvre dans `/admin`.
6. Vérifiez sa présence dans `/library`, puis ouvrez le lecteur.

L’approbation exige la confirmation des droits et un contenu disponible : au moins un chapitre publié, ou un fichier analysé et autorisé au téléchargement. Pour essayer le lecteur en ligne, ajoutez un chapitre publié. Les chapitres restés en brouillon ne sont pas affichés au public.

Les fichiers PDF, EPUB et TXT importés restent en quarantaine jusqu’à une analyse réussie avec **ClamAV**. Sans scanner opérationnel, testez la rédaction des chapitres dans l’éditeur ; un fichier bloqué en quarantaine ne pourra pas être utilisé comme un fichier validé. Le PDF est pris en charge comme pièce jointe téléchargeable, tandis que les imports textuels EPUB/TXT peuvent alimenter les chapitres.

## Dépannage

### `vendor/autoload.php` introuvable

Les dépendances PHP ne sont pas encore installées ou leur installation a été interrompue. Depuis la racine du projet :

```powershell
$env:COMPOSER_PROCESS_TIMEOUT = "1800"
composer setup
```

### `exceeded the timeout of 300 seconds`

Le processus lancé par Composer a dépassé sa limite de temps. Relancez les deux commandes ci-dessus dans la même fenêtre PowerShell et attendez la fin de l’installation. Les bibliothèques déjà installées pourront être réutilisées.

### `could not find driver` avec SQLite

Identifiez le fichier de configuration du PHP utilisé par le terminal :

```powershell
php --ini
php -m
```

Activez `pdo_sqlite` dans cette installation de PHP, puis vérifiez qu’il apparaît dans `php -m` avant de relancer `composer setup`. Si plusieurs versions de PHP sont installées, `where.exe php` indique celles trouvées dans le chemin système.

### `Vite manifest not found` ou interface non compilée

```powershell
npm.cmd ci --ignore-scripts
npm.cmd run build
```

Pour la configuration fournie, utilisez les fichiers compilés. La politique de sécurité du site n’est pas configurée par défaut pour le rechargement à chaud de Vite.

### `npm.ps1 cannot be loaded`

Dans PowerShell, utilisez `npm.cmd` pour les commandes npm, comme dans les exemples ci-dessus.

### L’e-mail de confirmation n’arrive pas

Vérifiez `MAIL_MAILER` dans `.env`. Avec `log`, aucun message n’est envoyé. Pour un essai local, utilisez la procédure Tinker décrite plus haut. Pour des messages réels, renseignez le transport SMTP dans `.env`, puis exécutez `php artisan config:clear`.

### L’espace auteur renvoie une erreur 403

Vérifiez que vous êtes connecté au bon compte et que celui-ci est auteur. Un compte lecteur confirmé peut devenir auteur depuis `/writers`.

### L’administrateur ne peut pas être créé

Si l’adresse est déjà utilisée, choisissez une autre adresse. Si le message `Use the administrator interface to manage additional administrators` apparaît, un administrateur existe déjà : connectez-vous avec ce compte pour gérer les accès.

## Commandes utiles

À exécuter depuis la racine du projet, dans un terminal disponible :

| Objectif | Commande |
| --- | --- |
| Lancer le serveur local | `php artisan serve` |
| Lancer le worker | `php artisan queue:work --tries=3 --timeout=120` |
| Lancer le planificateur local | `php artisan schedule:work` |
| Recompiler l’interface | `npm.cmd run build` |
| Recharger la configuration | `php artisan config:clear` |
| Tester le code PHP | `composer test` |
| Tester la pagination JavaScript | `npm.cmd test` |
| Vérifier les prérequis de l’application | `php artisan imary:doctor` |
| Vérifier les extensions et versions PHP requises | `composer check-platform-reqs` |

Le diagnostic peut signaler un échec pour le scanner si ClamAV n’est pas installé. Cela concerne notamment l’analyse des fichiers importés.

## Documentation

Pour un hébergement public, consultez le [guide de déploiement du projet](docs/DEPLOIEMENT.fr.md). `php artisan serve` et les paramètres locaux de ce document servent au développement. L’envoi réel des e-mails et l’analyse des fichiers doivent être configurés et vérifiés sur le serveur cible.

Références officielles :

- [Installer Composer sous Windows](https://getcomposer.org/doc/00-intro.md#installation-windows).
- [Configurer la durée maximale des processus Composer](https://getcomposer.org/doc/03-cli.md#composer-process-timeout).
- [Télécharger Node.js](https://nodejs.org/en/download).
- [Vérification des adresses e-mail dans Laravel](https://laravel.com/docs/13.x/verification).
