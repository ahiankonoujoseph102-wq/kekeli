// Réglages de l'agent, lus dans les variables d'environnement (fichier .env, jamais dans le code).
// Le modèle est celui de VOTRE compte (OpenAI, Gemini, Mistral, ou tout service compatible avec l'interface
// d'OpenAI) : vous payez directement votre fournisseur, avec votre propre clé.

export function lireReglages(env = process.env) {
  return {
    // Adresse de l'interface du modèle (compatible OpenAI). Par exemple https://api.openai.com/v1
    // ou https://generativelanguage.googleapis.com/v1beta/openai pour Gemini.
    adresseModele: String(env.MODELE_API_URL || 'https://api.openai.com/v1').replace(/\/+$/, ''),
    // Votre clé chez ce fournisseur. Elle reste sur le serveur : jamais dans le navigateur.
    cleModele: String(env.MODELE_API_CLE || ''),
    // Le nom du modèle chez votre fournisseur (voir .env.example).
    nomModele: String(env.MODELE_NOM || ''),
    port: Number(env.PORT || 3000),
    hote: String(env.HOTE || '0.0.0.0'),
    // Nombre de messages permis par minute pour une même personne (protection contre les abus).
    messagesParMinute: Math.max(1, Number(env.LIMITE_MESSAGES_MINUTE || 10)),
    // Sites qui peuvent afficher la bulle de discussion (adresses séparées par des virgules), par exemple
    // https://www.mon-salon.tg. Vide : seulement la page de l'agent elle-même.
    originesAutorisees: String(env.ORIGINES_AUTORISEES || '')
      .split(',')
      .map((origine) => origine.trim().replace(/\/+$/, ''))
      .filter(Boolean),
    // Clé de recherche sur Internet (Tavily), facultative : l'outil n'existe que si elle est remplie.
    cleRecherche: String(env.TAVILY_API_KEY || ''),
  }
}
