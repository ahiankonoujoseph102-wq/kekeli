# Mentions des œuvres de tiers

Ce fichier cite les projets de tiers dont Kekeli reprend des idées ou des informations. Le code de Kekeli reste soumis au fichier [`LICENSE`](LICENSE) (tous droits réservés) ; chaque projet cité ci-dessous reste soumis à sa propre licence.

## FreeLLMAPI

- Projet : FreeLLMAPI, https://github.com/tashfeenahmed/freellmapi
- Auteur : Tashfeen Ahmed
- Licence : MIT (Copyright (c) 2026 Tashfeen Ahmed)
- Ce que Kekeli en reprend (le 10 novembre 2026), réécrit dans son propre code, sans copier de fichier :
  - la liste des fournisseurs d'IA qui ont une offre gratuite, leurs modèles et leurs limites, utilisés comme point de départ de la vérification faite par Kekeli dans les conditions officielles de chaque fournisseur (`docs/plan.md`, partie 21) ;
  - l'idée de garder le même modèle pour une même conversation pendant 30 minutes (« sticky sessions ») : `MemoireModeles` dans `runner/src/fournisseurs.js` ;
  - l'idée d'un catalogue de modèles gratuits autorisés par fournisseur, et du passage au fournisseur suivant en cas de limite ou d'erreur, avec un compteur par fournisseur et par modèle (déjà présents dans Kekeli, complétés).
- **Depuis le 15 novembre 2026** (décision du porteur du projet, `docs/freellmapi.md`) : FreeLLMAPI est installé tel quel à côté du Runner, à une version figée (commit `bce469c3994f460b97cbf0e2b32fc2e47fcd4e88`), téléchargé au déploiement par `runner/freellmapi/installer.mjs` (jamais copié dans le dépôt). Son auteur le présente comme un outil d'expérimentation personnelle et rappelle que les conditions de chaque fournisseur s'appliquent : Kekeli ne branche que les fournisseurs dont les conditions permettent l'usage prévu. Le catalogue mis à jour en ligne (abonnement « Premium ») n'est pas utilisé.

Texte de la licence MIT de FreeLLMAPI :

> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Lexend (nom « kekeli » du logo)

- Police : Lexend, graisse 500, de Bonnie Shaver-Troup, Thomas Jockin et l'équipe Lexend (paquet `@fontsource/lexend`).
- Licence : SIL Open Font License 1.1 (OFL), qui permet de l'utiliser dans un logo, y compris commercial.
- Ce que Kekeli en reprend (le 14 novembre 2026) : seulement le mot « kekeli », transformé en tracés dans `public/logo-nom.svg` ; la police elle-même n'est ni installée ni servie par le site.

## unslop (outil de travail de Claude, pas une partie de Kekeli)

- Projet : unslop, https://github.com/heydamir/unslop (version du 9 novembre 2026, commit `97daa3e`)
- Auteur : Damir Sokolovsky ([@heydamir](https://x.com/heydamir), https://heydamir.design/unslop), créateur de la série « Unslopping » sur X
- Licence : Creative Commons Attribution 4.0 International (CC BY 4.0), https://creativecommons.org/licenses/by/4.0/ ; texte complet dans `.claude/skills/unslop/LICENSE`
- Ce qui est installé (le 14 novembre 2026) : le skill copié **sans modification** dans `.claude/skills/unslop`, pour ce projet seulement, comme le prévoit son README (« For a single project, copy it into `.claude/skills/` inside that project »). Il sert à Claude Code pour auditer le design de Kekeli et le corriger passe par passe (`docs/audit-unslop.md`). Il n'est ni chargé ni servi par le site de Kekeli.
- Ce qui n'est pas utilisé, à la demande du porteur du projet : la génération d'images payante (fal, Replicate, OpenAI images, Higgsfield) et la vidéo « avant/après » (`scripts/story.mjs`).
