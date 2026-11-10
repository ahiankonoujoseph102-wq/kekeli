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
- Kekeli n'installe pas FreeLLMAPI et n'utilise pas son service : son auteur le réserve à un usage personnel et expérimental.

Texte de la licence MIT de FreeLLMAPI :

> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
