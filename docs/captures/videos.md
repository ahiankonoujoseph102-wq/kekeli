# Montage vidéo et motion design (13 novembre 2026)

Kekeli écrit le code qui fabrique la vidéo (projet Revideo, licence MIT) et le lance dans un ordinateur E2B isolé, sans clé et sans Internet. Le client reçoit un fichier MP4, le regarde dans l'onglet « Vidéo », le télécharge, et demande des changements en écrivant (chaque changement fait une nouvelle version). Étude et choix : `docs/plan.md`, partie 26.18.

## Les quatre vidéos de l'essai réel

Vrai modèle, vraie voix off, vrais ordinateurs E2B, données inventées. Les coûts sont des **estimations** (prix officiels, taux 1 $ = 590 FCFA).

| Vidéo | Fichier | Durée | Rendu | Coût estimé |
|---|---|---|---|---|
| (a) Publicité TikTok verticale pour une boutique de pagnes, voix off, sous-titres, musique (formule Pro, de bout en bout par le site) | [a-publicite-tiktok-ama-pagnes.mp4](videos/a-publicite-tiktok-ama-pagnes.mp4) | 26,1 s (25 s demandées) | 115 s | environ 9 FCFA (16 FCFA avec la création de l'ordinateur et les images) |
| (a) Version 2 : « Mets le prix en plus gros et enlève les sous-titres » | [a-publicite-tiktok-ama-pagnes-version-2.mp4](videos/a-publicite-tiktok-ama-pagnes-version-2.mp4) | 27 s | 57 s | environ 7 FCFA |
| (b) Vidéo promotionnelle animée (motion design) de 50 secondes pour une école de formation, horizontale, avec son plan | [b-motion-design-ecole-50-secondes.mp4](videos/b-motion-design-ecole-50-secondes.mp4) | 51,9 s | 178 s | environ 24 FCFA |
| (c) Montage de 3 courtes vidéos et 2 photos, avec une musique africaine | [c-montage-videos-et-photos.mp4](videos/c-montage-videos-et-photos.mp4) | 25 s | 186 s | environ 7 FCFA |
| (d) Introduction YouTube horizontale de 10 secondes | [d-introduction-youtube-10-secondes.mp4](videos/d-introduction-youtube-10-secondes.mp4) | 10 s | 105 s | environ 5 FCFA |

Le premier essai de (a), avec des images créées par Kekeli : [a-premier-essai-avec-images-creees.mp4](videos/a-premier-essai-avec-images-creees.mp4) (défauts corrigés depuis : numéro affiché deux fois à la fin, sous-titre qui coupait le numéro). La version finale de (a) a des fonds animés : le quota gratuit du jour de création d'images (Cloudflare) était épuisé par les essais, et Kekeli a pris le secours prévu sans s'arrêter.

**Vérifications faites sur chaque vidéo** (automatiques dans l'ordinateur, puis relues ici) : durée, crête du son sous 0 dB (de −1,9 à −4,6 dB pour les versions finales, aucune saturation), aucun silence anormal, aucun texte trop long, images extraites à plusieurs moments ; sous-titres : [a-sous-titres.srt](videos/a-sous-titres.srt).

Images extraites à plusieurs moments :

![Publicité Ama Pagnes](videos/a-images.png)
![Version 2](videos/a-version-2-images.png)
![Motion design de l'école](videos/b-images.png)
![Montage des vidéos et des photos](videos/c-images.png)
![Introduction YouTube](videos/d-images.png)
![Premier essai de la publicité, avec images créées](videos/a-premier-essai-images.png)

## Les écrans

### Téléphone, mode clair

| Plan d'une vidéo de 50 secondes | Progression et « Arrêter » | Carte du fichier | Lecteur | Versions |
|---|---|---|---|---|
| ![](videos/ecrans/telephone-1-plan.png) | ![](videos/ecrans/telephone-2-progression.png) | ![](videos/ecrans/telephone-3-carte-du-fichier.png) | ![](videos/ecrans/telephone-4-lecteur.png) | ![](videos/ecrans/telephone-5-versions.png) |

### Téléphone, mode sombre

| Plan | Progression | Carte du fichier | Lecteur | Versions |
|---|---|---|---|---|
| ![](videos/ecrans/telephone-sombre-1-plan.png) | ![](videos/ecrans/telephone-sombre-2-progression.png) | ![](videos/ecrans/telephone-sombre-3-carte-du-fichier.png) | ![](videos/ecrans/telephone-sombre-4-lecteur.png) | ![](videos/ecrans/telephone-sombre-5-versions.png) |

### Ordinateur, mode clair

![Plan](videos/ecrans/ordinateur-1-plan.png)
![Progression](videos/ecrans/ordinateur-2-progression.png)
![Carte du fichier](videos/ecrans/ordinateur-3-carte-du-fichier.png)
![Lecteur](videos/ecrans/ordinateur-4-lecteur.png)
![Versions](videos/ecrans/ordinateur-5-versions.png)

### Ordinateur, mode sombre

![Plan](videos/ecrans/ordinateur-sombre-1-plan.png)
![Progression](videos/ecrans/ordinateur-sombre-2-progression.png)
![Carte du fichier](videos/ecrans/ordinateur-sombre-3-carte-du-fichier.png)
![Lecteur](videos/ecrans/ordinateur-sombre-4-lecteur.png)
![Versions](videos/ecrans/ordinateur-sombre-5-versions.png)

## Refaire l'essai et les captures

- Runner seul : `node runner/scripts/essai-video.mjs <dossier> <demande.json>` (exemples de demandes dans `docs/plan.md`, partie 26.18).
- De bout en bout : site, `php artisan queue:work` et Runner avec le même `KEKELI_RUNNER_SECRET`, `APP_URL=http://127.0.0.1:8123`, puis `php artisan tinker --execute="require 'scripts/essai-videos.php';"` (et `MODIFIER=1` pour la version 2).
- Captures : `php artisan tinker --execute="require 'scripts/preparer-captures-videos.php';"`, puis `node scripts/captures-videos.js http://127.0.0.1:8123 docs/captures/videos/ecrans '<identifiants affichés>'`.
