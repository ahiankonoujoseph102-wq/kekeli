"""Réglages de l'agent, lus dans les variables d'environnement (fichier .env, jamais dans le code).

Le modèle est celui de VOTRE compte (OpenAI, Gemini, Mistral, ou tout service compatible avec l'interface d'OpenAI) :
vous payez directement votre fournisseur, avec votre propre clé.
"""
import os


def lire_reglages(env=None):
    env = os.environ if env is None else env
    origines = [o.strip().rstrip('/') for o in str(env.get('ORIGINES_AUTORISEES', '')).split(',') if o.strip()]
    return {
        'adresse_modele': str(env.get('MODELE_API_URL') or 'https://api.openai.com/v1').rstrip('/'),
        'cle_modele': str(env.get('MODELE_API_CLE') or ''),
        'nom_modele': str(env.get('MODELE_NOM') or ''),
        'port': int(env.get('PORT') or 3000),
        'hote': str(env.get('HOTE') or '0.0.0.0'),
        'messages_par_minute': max(1, int(env.get('LIMITE_MESSAGES_MINUTE') or 10)),
        'origines_autorisees': origines,
    }
