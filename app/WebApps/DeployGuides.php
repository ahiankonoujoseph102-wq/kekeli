<?php

namespace App\WebApps;

/**
 * Step-by-step guides to put a web application online, in simple French, to follow from a phone, for each host.
 * The client chooses and pays for the host and the database. Never a password or a key in the code: only in
 * the settings of the host or in the « .env » file of the server. Screens of the hosts can change: the guides
 * are dated and kept short. Prices are estimates, to check on the host's site.
 *
 * Texts: `code` is shown as code, and **text** in bold.
 */
class DeployGuides
{
    public const UPDATED = '10 novembre 2026';

    /**
     * @return array<string, array{nom: string, pour: string, cout: string, code: string, etapes: list<array{0: string, 1: string}>, variables: list<array{0: string, 1: string}>, fichiers: list<array{0: string, 1: string}>}>
     */
    public function for(string $stack): array
    {
        return $stack === 'nextjs' ? $this->nextjs() : $this->laravel();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $stack, ?string $host): ?array
    {
        return $this->for($stack)[$host] ?? null;
    }

    private function laravel(): array
    {
        $partage = [
            ['APP_KEY', 'La clé de chiffrement donnée plus haut sur cette page (une seule fois, gardez-la secrète).'],
            ['APP_URL', 'L\'adresse de votre site, par exemple `https://mon-restaurant.com`.'],
            ['DB_HOST', '`localhost` le plus souvent.'],
            ['DB_DATABASE, DB_USERNAME, DB_PASSWORD', 'Le nom de la base, l\'utilisateur et le mot de passe créés à l\'étape de la base de données.'],
            ['ADMIN_EMAIL', 'Votre adresse électronique : le compte créé avec elle devient administrateur.'],
            ['INSTALLATION_AUTORISEE', '`oui` seulement le temps de créer les tables, puis `non`.'],
            ['MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS', 'Facultatif : l\'adresse électronique créée chez l\'hébergeur, pour les courriels « mot de passe oublié ».'],
        ];
        $fichiersPartage = [
            ['.htaccess', 'Fait passer toutes les adresses par le dossier « public » et cache les autres dossiers.'],
            ['.env.example', 'Modèle du fichier de réglages, sans aucun secret : à copier en « .env » sur le serveur.'],
            ['/installation', 'Page qui crée les tables de la base, seulement quand INSTALLATION_AUTORISEE=oui.'],
        ];

        return [
            'laravel-cloud' => [
                'nom' => 'Laravel Cloud',
                'pour' => 'Le plus simple pour Laravel : tout se fait depuis le téléphone, la base de données est réglée toute seule.',
                'cout' => 'Facturation selon l\'usage : quelques dollars par mois pour un petit site (estimation, à vérifier sur cloud.laravel.com).',
                'code' => 'github',
                'etapes' => [
                    ['Envoyez le code sur GitHub', 'Dans votre projet Kekeli, ouvrez l\'onglet **Code**, puis touchez **Connecter GitHub**. Le code part dans un dépôt privé de votre compte. Le fichier `.env` n\'est jamais envoyé.'],
                    ['Créez votre compte Laravel Cloud', 'Ouvrez **cloud.laravel.com** sur votre téléphone, créez un compte, puis reliez votre compte GitHub quand c\'est demandé.'],
                    ['Créez le projet', 'Touchez le bouton **New application** (son nom chez Laravel Cloud, en anglais), choisissez le dépôt de votre site, puis la région la plus proche de vos clients (par exemple en Europe), et validez.'],
                    ['Ajoutez la base de données', 'Dans l\'environnement du projet, ajoutez une base **MySQL** ou **Postgres**. Laravel Cloud remplit tout seul les réglages de connexion (DB_…).'],
                    ['Remplissez les réglages', 'Dans **Settings** puis **Environment variables**, ajoutez `ADMIN_EMAIL` (votre adresse) et `TRUSTED_PROXIES=*`. La clé `APP_KEY` est créée par Laravel Cloud.'],
                    ['Vérifiez la création des tables', 'Dans les commandes de déploiement (**Deploy commands**), gardez ou ajoutez `php artisan migrate --force`.'],
                    ['Mettez en ligne', 'Touchez **Deploy**. Quand le déploiement est vert, ouvrez l\'adresse donnée (…laravel.cloud).'],
                    ['Devenez administrateur', 'Sur votre site, touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL` : vous voyez alors le menu **Administration**.'],
                ],
                'variables' => [
                    ['ADMIN_EMAIL', 'Votre adresse électronique : le compte créé avec elle devient administrateur.'],
                    ['TRUSTED_PROXIES', '`*` (Laravel Cloud passe par un relais).'],
                    ['MAIL_…', 'Facultatif : réglages d\'envoi des courriels donnés par votre service de courriels.'],
                ],
                'fichiers' => [
                    ['.env.example', 'Liste des réglages, sans aucun secret.'],
                    ['README.md', 'Explications du projet.'],
                ],
            ],
            'hostinger' => [
                'nom' => 'Hostinger',
                'pour' => 'Hébergement web mutualisé, souvent avec un nom de domaine : sans terminal, avec le fichier ZIP préparé par Kekeli.',
                'cout' => 'Environ 3 à 4 $ par mois (offres Premium ou Business), nom de domaine souvent offert la première année (estimation, à vérifier sur hostinger.fr).',
                'code' => 'paquet',
                'etapes' => [
                    ['Préparez le fichier ZIP', 'Plus haut sur cette page, touchez **Préparer le fichier ZIP**, puis **Télécharger**. Il contient votre site et ses bibliothèques, sans aucun mot de passe.'],
                    ['Créez la base de données', 'Dans hPanel : **Sites web** → **Gérer** → **Bases de données** → **Bases de données MySQL**. Créez une base, un utilisateur et un mot de passe. Notez-les dans un endroit sûr, jamais dans le code.'],
                    ['Choisissez PHP 8.3', 'Dans **Avancé** → **Configuration PHP**, choisissez la version **8.3** (8.2 au moins).'],
                    ['Déposez les fichiers', '**Fichiers** → **Gestionnaire de fichiers** → dossier `public_html`. Téléversez le fichier ZIP, puis touchez-le et choisissez **Extraire** dans ce même dossier.'],
                    ['Créez le fichier .env', 'Toujours dans `public_html`, affichez les fichiers cachés, copiez `.env.example` en `.env`, puis modifiez `.env` avec les valeurs du tableau « Réglages à remplir » ci-dessous. Mettez `INSTALLATION_AUTORISEE=oui`.'],
                    ['Créez les tables', 'Ouvrez `https://votre-domaine/installation` : la page dit « Installation terminée ». Remettez ensuite `INSTALLATION_AUTORISEE=non` dans `.env`.'],
                    ['Devenez administrateur', 'Ouvrez votre site, touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL`.'],
                    ['Courriels (facultatif)', 'Dans **Courriels**, créez une adresse (par exemple contact@votre-domaine), puis mettez ses réglages SMTP dans `.env` (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS).'],
                ],
                'variables' => $partage,
                'fichiers' => $fichiersPartage,
            ],
            'cpanel' => [
                'nom' => 'Hébergement mutualisé avec cPanel',
                'pour' => 'Le plus courant en Afrique (beaucoup d\'hébergeurs locaux) : sans terminal, avec le fichier ZIP préparé par Kekeli.',
                'cout' => 'Souvent entre 2 000 et 10 000 FCFA par mois selon l\'hébergeur (estimation, à vérifier chez l\'hébergeur).',
                'code' => 'paquet',
                'etapes' => [
                    ['Préparez le fichier ZIP', 'Plus haut sur cette page, touchez **Préparer le fichier ZIP**, puis **Télécharger**. Il contient votre site et ses bibliothèques, sans aucun mot de passe.'],
                    ['Créez la base de données', 'Dans cPanel, ouvrez **Assistant de base de données MySQL** (MySQL Database Wizard). Créez une base, un utilisateur et un mot de passe, puis donnez **tous les droits** à l\'utilisateur. Attention : cPanel ajoute souvent votre identifiant devant les noms (par exemple `moncompte_resto`). Notez-les dans un endroit sûr.'],
                    ['Choisissez PHP 8.3', 'Ouvrez **Sélectionner une version de PHP** (Select PHP Version) ou **MultiPHP Manager**, et choisissez **8.3** (8.2 au moins) pour votre domaine.'],
                    ['Déposez les fichiers', 'Ouvrez le **Gestionnaire de fichiers**, entrez dans `public_html` (ou le dossier de votre domaine), téléversez le fichier ZIP, puis choisissez **Extraire**.'],
                    ['Créez le fichier .env', 'Dans **Paramètres**, cochez « Afficher les fichiers cachés ». Copiez `.env.example` en `.env`, puis modifiez `.env` avec les valeurs du tableau « Réglages à remplir » ci-dessous. Mettez `INSTALLATION_AUTORISEE=oui`.'],
                    ['Créez les tables', 'Ouvrez `https://votre-domaine/installation` : la page dit « Installation terminée ». Remettez ensuite `INSTALLATION_AUTORISEE=non` dans `.env`.'],
                    ['Devenez administrateur', 'Ouvrez votre site, touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL`.'],
                    ['Courriels (facultatif)', 'Dans **Comptes de messagerie**, créez une adresse, puis mettez ses réglages dans `.env` (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS).'],
                ],
                'variables' => $partage,
                'fichiers' => $fichiersPartage,
            ],
            'render' => [
                'nom' => 'Render',
                'pour' => 'Service et base PostgreSQL créés ensemble à partir du fichier render.yaml préparé par Kekeli.',
                'cout' => 'Offre gratuite pour essayer (le site s\'endort sans visite, la base gratuite est limitée dans le temps) ; environ 7 $ par mois pour un site toujours allumé, plus la base (estimations, à vérifier sur render.com).',
                'code' => 'github',
                'etapes' => [
                    ['Envoyez le code sur GitHub', 'Dans votre projet Kekeli, ouvrez l\'onglet **Code**, puis touchez **Connecter GitHub**. Le fichier `.env` n\'est jamais envoyé.'],
                    ['Créez votre compte Render', 'Ouvrez **render.com**, créez un compte avec GitHub et autorisez l\'accès au dépôt de votre site.'],
                    ['Créez tout d\'un coup', 'Touchez **New** → **Blueprint**, choisissez le dépôt : Render lit le fichier `render.yaml` et prépare le site et la base de données PostgreSQL.'],
                    ['Remplissez les réglages demandés', '`APP_KEY` : la clé donnée plus haut sur cette page. `ADMIN_EMAIL` : votre adresse. `APP_URL` : laissez vide pour le premier essai, puis mettez l\'adresse …onrender.com donnée par Render.'],
                    ['Mettez en ligne', 'Touchez **Apply**. Render construit le site (quelques minutes) et crée les tables à chaque démarrage.'],
                    ['Devenez administrateur', 'Ouvrez l\'adresse …onrender.com, touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL`.'],
                ],
                'variables' => [
                    ['APP_KEY', 'La clé de chiffrement donnée plus haut sur cette page (gardez-la secrète).'],
                    ['APP_URL', 'L\'adresse …onrender.com de votre site.'],
                    ['ADMIN_EMAIL', 'Votre adresse électronique : le compte créé avec elle devient administrateur.'],
                    ['DB_URL', 'Rempli tout seul par Render (base PostgreSQL du fichier render.yaml).'],
                ],
                'fichiers' => [
                    ['render.yaml', 'Décrit le site et la base de données pour Render.'],
                    ['Dockerfile', 'Prépare le serveur du site (PHP 8.3) ; les tables sont créées au démarrage.'],
                    ['.env.example', 'Liste des réglages, sans aucun secret.'],
                ],
            ],
        ];
    }

    private function nextjs(): array
    {
        return [
            'vercel' => [
                'nom' => 'Vercel',
                'pour' => 'Le plus simple pour Next.js, avec une base PostgreSQL Neon ajoutée en quelques gestes.',
                'cout' => 'Offre « Hobby » gratuite pour un usage personnel ; pour une activité commerciale, Vercel demande l\'offre « Pro » (environ 20 $ par mois). Base Neon : offre gratuite limitée (estimations, à vérifier sur vercel.com et neon.com).',
                'code' => 'github',
                'etapes' => [
                    ['Envoyez le code sur GitHub', 'Dans votre projet Kekeli, ouvrez l\'onglet **Code**, puis touchez **Connecter GitHub**. Le fichier `.env` n\'est jamais envoyé.'],
                    ['Créez votre compte Vercel', 'Ouvrez **vercel.com**, créez un compte avec GitHub.'],
                    ['Importez le site', 'Touchez **Add New…** → **Project**, puis **Import** à côté du dépôt de votre site. Vercel reconnaît Next.js tout seul.'],
                    ['Remplissez les réglages', 'Dans **Environment Variables**, ajoutez `ADMIN_EMAIL` (votre adresse). Touchez **Deploy**.'],
                    ['Ajoutez la base de données', 'Dans votre projet Vercel : onglet **Storage** → **Create Database** → **Neon** (PostgreSQL) → reliez-la au projet. La variable `DATABASE_URL` est ajoutée toute seule. Puis **Deployments** → **Redeploy**.'],
                    ['Mettez l\'adresse du site', 'Ajoutez `APP_URL` avec l\'adresse …vercel.app de votre site (pour les liens « mot de passe oublié »), puis redéployez.'],
                    ['Devenez administrateur', 'Ouvrez votre site : les tables se créent toutes seules à la première visite. Touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL`.'],
                ],
                'variables' => [
                    ['ADMIN_EMAIL', 'Votre adresse électronique : le compte créé avec elle devient administrateur.'],
                    ['DATABASE_URL', 'Remplie toute seule quand vous reliez la base Neon.'],
                    ['APP_URL', 'L\'adresse …vercel.app de votre site.'],
                    ['NEXT_PUBLIC_APP_NAME', 'Facultatif : le nom affiché de votre site.'],
                    ['SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM', 'Facultatif : réglages d\'envoi des courriels (mot de passe oublié).'],
                ],
                'fichiers' => [
                    ['.env.example', 'Liste des réglages, sans aucun secret.'],
                    ['lib/migrations.js', 'Tables de la base, créées toutes seules au premier démarrage.'],
                ],
            ],
            'render' => [
                'nom' => 'Render',
                'pour' => 'Site Node.js et base PostgreSQL créés ensemble à partir du fichier render.yaml préparé par Kekeli.',
                'cout' => 'Offre gratuite pour essayer (le site s\'endort sans visite, la base gratuite est limitée dans le temps) ; environ 7 $ par mois pour un site toujours allumé, plus la base (estimations, à vérifier sur render.com).',
                'code' => 'github',
                'etapes' => [
                    ['Envoyez le code sur GitHub', 'Dans votre projet Kekeli, ouvrez l\'onglet **Code**, puis touchez **Connecter GitHub**. Le fichier `.env` n\'est jamais envoyé.'],
                    ['Créez votre compte Render', 'Ouvrez **render.com**, créez un compte avec GitHub et autorisez l\'accès au dépôt de votre site.'],
                    ['Créez tout d\'un coup', 'Touchez **New** → **Blueprint**, choisissez le dépôt : Render lit le fichier `render.yaml` et prépare le site et la base PostgreSQL.'],
                    ['Remplissez les réglages demandés', '`ADMIN_EMAIL` : votre adresse. `APP_URL` : laissez vide pour le premier essai, puis mettez l\'adresse …onrender.com donnée par Render.'],
                    ['Mettez en ligne', 'Touchez **Apply**. Render construit le site (quelques minutes) ; les tables se créent toutes seules à la première visite.'],
                    ['Devenez administrateur', 'Ouvrez l\'adresse …onrender.com, touchez **Créer un compte** avec l\'adresse mise dans `ADMIN_EMAIL`.'],
                ],
                'variables' => [
                    ['ADMIN_EMAIL', 'Votre adresse électronique : le compte créé avec elle devient administrateur.'],
                    ['DATABASE_URL', 'Remplie toute seule par Render (base PostgreSQL du fichier render.yaml).'],
                    ['APP_URL', 'L\'adresse …onrender.com de votre site.'],
                ],
                'fichiers' => [
                    ['render.yaml', 'Décrit le site et la base de données pour Render.'],
                    ['.env.example', 'Liste des réglages, sans aucun secret.'],
                ],
            ],
        ];
    }
}
