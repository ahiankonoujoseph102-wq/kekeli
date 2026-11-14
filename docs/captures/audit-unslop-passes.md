# Audit unslop : les passes, une à une

Décisions du porteur du projet (14 novembre 2026) : **1B** (lueur adoucie), **2A** (onglets des réglages sur une seule ligne qui défile), **3 oui** (moins de gras dans les réponses, annulé si la mesure sur 33 phrases est moins bonne), **4 oui** (nouveaux reçus plus sobres). Plan : [`docs/audit-unslop.md`](../audit-unslop.md). Captures prises sur un téléphone simulé (Pixel 6) et sur un ordinateur, en clair et en sombre, avec des données inventées (`scripts/captures-audit-unslop.js`).

Poids de départ de l'interface : CSS 350,4 ko (41,2 ko compressé), JavaScript 4,8 ko (1,9 ko compressé).

## Passe 0 : défauts

- **Bouton « Copier » de la bulle d'un agent** : il donnait `:aria-label="__('Copy')"` à Alpine, qui l'exécutait comme du JavaScript (« __ is not defined ») ; le bouton n'avait pas de nom pour les lecteurs d'écran. Il a maintenant `aria-label` et `title` « Copier », puis « Copié » après la copie. Nouveau test : aucune balise HTML simple ne donne une traduction à Alpine (`tests/Feature/DesignTest.php`) ; le test des agents vérifie le nom du bouton.
- **Durée de la vidéo d'exemple** des captures : « 26 secondes » au lieu de « 1 seconde » (seulement le script `scripts/preparer-captures-design.php` ; une vraie vidéo envoie déjà sa durée).
- Console du navigateur : plus aucune erreur sur la page d'un agent. Poids du CSS et du JavaScript inchangés (aucun style touché).

| Fenêtre « Le code de votre agent », téléphone clair | Téléphone sombre | Ordinateur clair | Vidéo, téléphone clair |
|---|---|---|---|
| ![](audit-unslop/passes/passe-0/telephone-clair-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/telephone-sombre-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/ordinateur-clair-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/telephone-clair-26-video.jpg) |

## Passe 1 : espacements

- **Une seule marge intérieure par genre de carte** : 16 pixels sur téléphone et 20 sur ordinateur pour une carte seule (Formules, Réglages → Formule, Paiement, Administration → Modèles), 16 pixels pour les cartes d'une liste (tuiles de Consommation, Projets, Paiements et Réglages de l'administration, Variables secrètes). Avant : 12, 16 et 20 pixels mélangés. La carte « Votre formule » prend la classe commune `kekeli-carte` (elle avait son propre coin et son propre fond).
- **« Récents » mieux séparé** dans la barre latérale : 12 pixels au-dessus, 8 en dessous (avant : collé au groupe du dessus).
- Nouveau test : `test_cards_share_one_padding` (`tests/Feature/DesignTest.php`).
- Poids : CSS 350,4 → 351,8 ko (41,2 → 41,4 ko compressé), à cause des classes de marge pour l'ordinateur ; JavaScript inchangé.

| Consommation, avant | Après | Formules, avant | Après | Menu, avant | Après |
|---|---|---|---|---|---|
| ![](audit-unslop/ecrans/telephone-clair-42-admin-consommation.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-42-admin-consommation.jpg) | ![](audit-unslop/ecrans/telephone-clair-36-formules.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-36-formules.jpg) | ![](audit-unslop/ecrans/telephone-clair-08-menu.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-08-menu.jpg) |

Toutes les captures de la passe (téléphone et ordinateur, clair et sombre) : `docs/captures/audit-unslop/passes/passe-1/`.
