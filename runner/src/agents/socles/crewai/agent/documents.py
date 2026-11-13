"""Documents de l'entreprise (dossier documents/) : découpés en passages ; les plus proches de la question sont donnés
aux agents. Simple, gratuit, sans base de vecteurs : suffisant pour quelques dizaines de pages."""
import re
import unicodedata
from pathlib import Path

MOTS_VIDES = set('le la les un une des de du et ou a à au aux en dans pour par sur avec est sont ce cette ces que qui quoi quel quelle quels quelles comment combien je tu il elle nous vous ils elles mon ma mes ton ta tes son sa ses votre vos notre nos y ne pas plus'.split())


# Les mots d'un même sujet se retrouvent : une question sur un jour ou une heure trouve les horaires.
SUJETS = {'horaire': re.compile(r'^(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche|ouvert|ouverte|ouvre|ouvrez|ouverture|ferme|fermee|fermez|fermeture|horaire|heure)$')}


def mots(texte):
    sans_accents = unicodedata.normalize('NFD', str(texte).lower())
    sans_accents = ''.join(c for c in sans_accents if unicodedata.category(c) != 'Mn')
    brut = [m for m in re.split(r'[^a-z0-9]+', sans_accents) if len(m) > 1 and m not in MOTS_VIDES]
    # Singulier simple : « ouverts » trouve « ouvert », « pagnes » trouve « pagne ».
    brut = [m[:-1] if len(m) > 3 and m[-1] in 'sx' else m for m in brut]
    return brut + [sujet for sujet, forme in SUJETS.items() if any(forme.match(m) for m in brut)]


def charger_documents(dossier):
    passages = []
    dossier = Path(dossier)
    if not dossier.is_dir():
        return passages
    for fichier in sorted(dossier.iterdir()):
        if fichier.suffix.lower() not in ('.md', '.txt'):
            continue
        for bloc in re.split(r'\n(?=#{1,3} )|\n{2,}', fichier.read_text(encoding='utf-8')):
            propre = bloc.strip()
            if propre:
                passages.append({'source': fichier.name, 'texte': propre[:2000], 'mots': set(mots(propre))})
    return passages


def passages_utiles(passages, question, nombre=4):
    mots_question = mots(question)
    notes = [(sum(1 for m in mots_question if m in p['mots']), p) for p in passages]
    return [p for note, p in sorted((x for x in notes if x[0] > 0), key=lambda x: -x[0])[:nombre]]
