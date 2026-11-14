"""Serveur de l'agent : la page de discussion, la bulle à intégrer sur un site, et l'adresse /api/message.
La clé du modèle reste ici, sur le serveur : le navigateur ne la voit jamais."""
import json
import re
import time
from collections import defaultdict
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

from agent.equipe import Equipe
from agent.reglages import lire_reglages

RACINE = Path(__file__).resolve().parent
MESSAGE_MAX = 2000
CONVERSATION = re.compile(r'^[A-Za-z0-9_-]{8,64}$')


def creer_limiteur(par_minute, maintenant=time.monotonic):
    vus = defaultdict(list)

    def permis(cle):
        t = maintenant()
        recents = [m for m in vus[cle] if t - m < 60]
        if len(recents) >= par_minute:
            vus[cle] = recents
            return False
        recents.append(t)
        vus[cle] = recents
        return True

    return permis


def message_erreur(erreur):
    texte = str(erreur)
    if re.search(r'essai gratuit|variables secrètes', texte, re.I):
        phrase = re.search(r"(L'essai gratuit[^\"}]*)", texte)
        return 429, phrase.group(1).strip() if phrase else texte
    if '429' in texte:
        return 429, "L'assistant reçoit beaucoup de messages. Réessayez dans un instant."
    return 502, "L'assistant n'a pas pu répondre. Réessayez dans un instant."


def creer_gestionnaire(reglages, equipe=None):
    limiter = creer_limiteur(reglages['messages_par_minute'])
    pret = equipe is not None or bool(reglages['cle_modele'] and reglages['nom_modele'])
    equipe = equipe or (Equipe(reglages) if pret else None)
    page = (RACINE / 'public' / 'index.html').read_bytes()
    bulle = (RACINE / 'public' / 'bulle.js').read_bytes()
    texte = (RACINE / 'public' / 'texte.js').read_bytes()
    ancetres = {'Content-Security-Policy': "frame-ancestors 'self' " + ' '.join(reglages['origines_autorisees'])} if reglages['origines_autorisees'] else {}

    class Gestionnaire(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def _envoyer(self, statut, corps, type_='application/json; charset=utf-8', entetes=None):
            donnees = corps if isinstance(corps, bytes) else json.dumps(corps, ensure_ascii=False).encode('utf-8')
            self.send_response(statut)
            self.send_header('Content-Type', type_)
            self.send_header('Cache-Control', 'no-store')
            for nom, valeur in (entetes or {}).items():
                self.send_header(nom, valeur)
            self.end_headers()
            self.wfile.write(donnees)

        def do_GET(self):
            if self.path in ('/', '/index.html') or self.path.startswith('/?'):
                return self._envoyer(200, page, 'text/html; charset=utf-8', ancetres)
            if self.path == '/bulle.js':
                return self._envoyer(200, bulle, 'text/javascript; charset=utf-8')
            if self.path == '/texte.js':
                return self._envoyer(200, texte, 'text/javascript; charset=utf-8')
            if self.path == '/sante':
                return self._envoyer(200, {'ok': True, 'pret': pret})
            self._envoyer(404, {'erreur': 'Adresse inconnue.'})

        def do_POST(self):
            if self.path != '/api/message':
                return self._envoyer(404, {'erreur': 'Adresse inconnue.'})
            visiteur = (self.headers.get('X-Forwarded-For') or '').split(',')[0].strip() or self.client_address[0]
            if not limiter(visiteur):
                return self._envoyer(429, {'erreur': 'Vous envoyez beaucoup de messages. Attendez une minute, puis réessayez.'})
            try:
                taille = min(int(self.headers.get('Content-Length') or 0), 20000)
                demande = json.loads(self.rfile.read(taille) or b'{}')
            except (ValueError, json.JSONDecodeError):
                return self._envoyer(400, {'erreur': 'Message illisible.'})
            message = str(demande.get('message') or '').strip()
            if not CONVERSATION.match(str(demande.get('conversation') or '')):
                return self._envoyer(400, {'erreur': 'Conversation inconnue.'})
            if not message:
                return self._envoyer(400, {'erreur': 'Le message est vide.'})
            if len(message) > MESSAGE_MAX:
                return self._envoyer(400, {'erreur': f'Le message est trop long ({MESSAGE_MAX} caractères au plus).'})
            if equipe is None:
                return self._envoyer(503, {'erreur': "L'assistant n'est pas encore branché à son modèle. Revenez un peu plus tard."})
            try:
                resultat = equipe.repondre(demande['conversation'], message)
                # Une équipe peut aussi dire qui a agi ; montré seulement pendant les essais de Kekeli (AFFICHER_PARCOURS).
                if isinstance(resultat, dict):
                    corps = {'reponse': resultat.get('reponse', '')}
                    if reglages.get('afficher_parcours') and isinstance(resultat.get('parcours'), list):
                        corps['parcours'] = resultat['parcours']
                    return self._envoyer(200, corps)
                return self._envoyer(200, {'reponse': resultat})
            except Exception as erreur:  # le détail reste dans le journal du serveur
                print(f'Réponse impossible : {erreur}')
                statut, texte = message_erreur(erreur)
                return self._envoyer(statut, {'erreur': texte})

    return Gestionnaire


if __name__ == '__main__':
    reglages = lire_reglages()
    print(f"Agent prêt sur http://{reglages['hote']}:{reglages['port']}")
    ThreadingHTTPServer((reglages['hote'], reglages['port']), creer_gestionnaire(reglages)).serve_forever()
