#!/usr/bin/env python3
# Moteur de composition des images et des affiches de Kekeli, copié dans l'ordinateur E2B isolé (sans clé, sans
# Internet après l'installation). Le fond vient du modèle d'images (ou d'une photo du client) ; TOUT le texte est posé
# ici, par le code, avec des polices libres (Inter, licence OFL) : il est donc toujours exact (prix, numéros, accents).
#
#   python3 kekeli_images.py composer <structure.json> <dossier> [fond.png] [logo.png] [photo.png]
#
# Bibliothèque : Pillow (licence HPND, libre).

import glob
import json
import os
import re
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageOps

# Tailles en pixels : réseaux sociaux en 1080 de large ; impression en 200 points par pouce.
FORMATS = {
    'carre': (1080, 1080),
    'vertical': (1080, 1920),
    'paysage': (1200, 630),
    'a4': (1654, 2339),
    'a3': (2339, 3307),
}
IMPRESSION = {'a4': 200, 'a3': 200}
POLICES = '/usr/share/fonts/opentype/inter'


def police(nom, taille):
    # Inter (licence OFL) : paquet « fonts-inter » ; selon sa version, « Inter Display » n'existe pas (Inter le remplace).
    noms = [nom, nom.replace('InterDisplay', 'Inter')]
    candidats = [c for n in noms for c in (f'{POLICES}/{n}.otf', f'{POLICES}/{n}.ttf')]
    candidats += [c for n in noms for c in sorted(glob.glob(f'/usr/share/fonts/**/{n}.*', recursive=True))]
    candidats.append('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')
    for chemin in candidats:
        if os.path.exists(chemin):
            return ImageFont.truetype(chemin, max(8, int(taille)))
    return ImageFont.load_default()


def couleur(valeur, defaut):
    texte = str(valeur or '').strip().lstrip('#')
    return tuple(int(texte[i:i + 2], 16) for i in (0, 2, 4)) if re.fullmatch(r'[0-9A-Fa-f]{6}', texte) else defaut


def propre(valeur, maximum=200):
    return re.sub(r'[\x00-\x1f]', ' ', str(valeur or '')).strip()[:maximum]


def lignes_qui_tiennent(dessin, texte, nom, taille, largeur, lignes_max, minimum):
    """Le texte coupé en lignes à la largeur voulue, à la plus grande taille possible (jamais coupé ni débordant)."""
    while taille >= minimum:
        fonte = police(nom, taille)
        mots, lignes, courante = texte.split(), [], ''
        for mot in mots:
            essai = (courante + ' ' + mot).strip()
            if dessin.textlength(essai, font=fonte) <= largeur:
                courante = essai
            else:
                if courante:
                    lignes.append(courante)
                courante = mot
        if courante:
            lignes.append(courante)
        if len(lignes) <= lignes_max and all(dessin.textlength(l, font=fonte) <= largeur for l in lignes):
            return fonte, lignes
        taille -= max(1, int(taille * 0.06))
    fonte = police(nom, minimum)
    return fonte, [texte]


def couvrir(image, taille):
    return ImageOps.fit(image.convert('RGB'), taille, method=Image.LANCZOS, centering=(0.5, 0.45))


def fond_uni(taille, base):
    """Un dégradé sobre quand il n'y a pas d'image de fond."""
    largeur, hauteur = taille
    haut = tuple(min(255, int(c * 1.15 + 12)) for c in base)
    bas = tuple(int(c * 0.7) for c in base)
    degrade = Image.new('RGB', (1, hauteur))
    for y in range(hauteur):
        t = y / max(1, hauteur - 1)
        degrade.putpixel((0, y), tuple(int(haut[i] * (1 - t) + bas[i] * t) for i in range(3)))
    return degrade.resize(taille)


def voile(image, position, force):
    """Un dégradé sombre là où va le texte, pour qu'il reste lisible sur n'importe quelle image."""
    largeur, hauteur = image.size
    masque = Image.new('L', (1, hauteur))
    for y in range(hauteur):
        t = y / max(1, hauteur - 1)
        if position == 'haut':
            t = 1 - t
        if position == 'centre':
            valeur = int(force * 0.75)
        else:
            valeur = int(force * max(0.0, min(1.0, (t - 0.25) / 0.55)))
        masque.putpixel((0, y), valeur)
    masque = masque.resize((largeur, hauteur))
    noir = Image.new('RGB', (largeur, hauteur), (12, 10, 8))
    return Image.composite(noir, image, masque)


def voile_zone(image, haut, bas, force):
    """Assombrit la bande [haut, bas] (pleine force), avec un fondu de 18 % de la hauteur au-dessus et au-dessous."""
    largeur, hauteur = image.size
    fondu = max(1.0, hauteur * 0.18)
    masque = Image.new('L', (1, hauteur))
    for y in range(hauteur):
        if haut <= y <= bas:
            t = 1.0
        elif y < haut:
            t = max(0.0, 1 - (haut - y) / fondu)
        else:
            t = max(0.0, 1 - (y - bas) / fondu)
        masque.putpixel((0, y), int(255 * force * (t * t * (3 - 2 * t))))
    masque = masque.resize((largeur, hauteur))
    return Image.composite(Image.new('RGB', (largeur, hauteur), (12, 10, 8)), image, masque)


def coins_arrondis(image, rayon):
    masque = Image.new('L', image.size, 0)
    ImageDraw.Draw(masque).rounded_rectangle([0, 0, image.size[0] - 1, image.size[1] - 1], rayon, fill=255)
    resultat = Image.new('RGBA', image.size)
    resultat.paste(image, (0, 0), masque)
    return resultat


def composer(structure, dossier, fond=None, logo=None, photo=None):
    format_ = structure.get('format') if structure.get('format') in FORMATS else 'carre'
    largeur, hauteur = FORMATS[format_]
    unite = largeur / 1080
    marge = int(largeur * 0.07)
    theme = structure.get('theme') or {}
    accent = couleur(theme.get('couleur'), (227, 160, 8))
    base = couleur((structure.get('fond') or {}).get('couleur'), (31, 58, 95))
    textes = structure.get('textes') or {}
    styles = structure.get('styles') or {}

    def echelle(champ):
        # La taille demandée par le client (« mets le prix en plus gros » : 1,5) ; 1 = normale.
        try:
            return max(0.6, min(2.4, float((styles.get(champ) or {}).get('taille', 1))))
        except (TypeError, ValueError):
            return 1.0

    def teinte_de(champ, defaut):
        return couleur((styles.get(champ) or {}).get('couleur'), defaut)

    a_du_texte = any(propre(textes.get(c)) for c in ('titre', 'sous_titre', 'prix', 'date', 'lieu', 'contact')) or bool(textes.get('details'))

    # Le fond : la photo du client d'abord, sinon l'image créée, sinon un dégradé sobre.
    source = photo or fond
    image = couvrir(Image.open(source), (largeur, hauteur)) if source and os.path.exists(source) else fond_uni((largeur, hauteur), base)
    position = structure.get('disposition') if structure.get('disposition') in ('haut', 'bas', 'centre') else 'bas'
    dessin = ImageDraw.Draw(image)
    poses = []
    blanc = (255, 255, 255)
    doux = (232, 228, 220)
    utile = largeur - 2 * marge

    if a_du_texte:
        # Les blocs de texte, du haut vers le bas, avec leur hauteur ; puis placés selon la disposition.
        blocs = []
        surtitre = propre(textes.get('surtitre'), 60).upper()
        if surtitre:
            blocs.append(('surtitre', *lignes_qui_tiennent(dessin, surtitre, 'Inter-Bold', 34 * unite * echelle('surtitre'), utile, 1, 20 * unite)))
        titre = propre(textes.get('titre'), 90)
        if titre:
            blocs.append(('titre', *lignes_qui_tiennent(dessin, titre, 'InterDisplay-Black', (118 if format_ != 'paysage' else 80) * unite * echelle('titre'), utile, 3 if echelle('titre') <= 1 else 4, 44 * unite * min(1.0, echelle('titre')))))
        sous_titre = propre(textes.get('sous_titre'), 160)
        if sous_titre:
            blocs.append(('sous_titre', *lignes_qui_tiennent(dessin, sous_titre, 'Inter-SemiBold', 44 * unite * echelle('sous_titre'), utile, 3, 26 * unite)))
        for detail in [propre(d, 120) for d in (textes.get('details') or [])][:4]:
            if detail:
                blocs.append(('detail', *lignes_qui_tiennent(dessin, '•  ' + detail, 'Inter-Medium', 40 * unite * echelle('details'), utile, 2, 24 * unite)))

        espace = int(18 * unite)
        hauteurs = []
        for genre, fonte, lignes in blocs:
            interligne = fonte.size * (1.06 if genre == 'titre' else 1.3)
            hauteurs.append(int(interligne * len(lignes)) + (espace * 2 if genre == 'titre' else espace))

        # Le prix, dans une étiquette à la couleur du thème ; le pied (date, lieu, contact, paiement).
        prix = propre(textes.get('prix'), 40)
        prix_barre = propre(textes.get('prix_barre'), 40)
        pied = [(c, propre(textes.get(c), m)) for c, m in (('date', 80), ('lieu', 100), ('contact', 80), ('paiement', 80))]
        pied = [(c, t) for c, t in pied if t]
        # Le prix normal occupe au plus 60 % de la largeur : « plus gros » reste ainsi net et visible.
        taille_prix = 78 * unite
        if prix:
            taille_prix = lignes_qui_tiennent(dessin, prix, 'InterDisplay-Black', 78 * unite, int(utile * 0.6), 1, 30 * unite)[0].size
        taille_prix = lignes_qui_tiennent(dessin, prix or '0', 'InterDisplay-Black', taille_prix * echelle('prix'), utile - int(60 * unite), 1, 30 * unite)[0].size
        fonte_prix = police('InterDisplay-Black', taille_prix)
        hauteur_prix = int(fonte_prix.size * 1.6) + (int(40 * unite) if prix_barre else 0) if prix else 0
        # Le pied : chaque morceau avec sa taille et sa couleur (« la date en plus gros », « le contact en jaune »).
        lignes_pied = []
        for champ, morceau in pied:
            fonte_morceau, lignes = lignes_qui_tiennent(dessin, morceau, 'Inter-SemiBold', 36 * unite * echelle(champ), utile, 2, 22 * unite)
            lignes_pied += [(fonte_morceau, ligne, teinte_de(champ, (255, 255, 255))) for ligne in lignes]
        hauteur_pied = sum(int(f.size * 1.45) for f, _, _ in lignes_pied) + (int(26 * unite) if lignes_pied else 0)

        total = sum(hauteurs) + hauteur_prix + hauteur_pied
        if position == 'haut':
            y = marge + (int(min(largeur, hauteur) * 0.17 + 40 * unite) if logo else 0)
        elif position == 'centre':
            y = max(marge, (hauteur - total) // 2)
        else:
            y = max(marge, hauteur - marge - total)

        # Un voile sombre exactement derrière le bloc de texte (fondu en haut et en bas) : lisible sur tout fond.
        if structure.get('voile', True):
            image = voile_zone(image, y - marge * 0.5, y + total + marge * 0.3, 0.86 if structure.get('usage') == 'affiche' else 0.8)
            dessin = ImageDraw.Draw(image)
        for (genre, fonte, lignes), h in zip(blocs, hauteurs):
            interligne = fonte.size * (1.06 if genre == 'titre' else 1.3)
            champ = {'detail': 'details'}.get(genre, genre)
            teinte = teinte_de(champ, accent if genre == 'surtitre' else (blanc if genre in ('titre',) else doux))
            for i, ligne in enumerate(lignes):
                dessin.text((marge, y + int(i * interligne)), ligne, font=fonte, fill=teinte)
            poses.append(' '.join(lignes).replace('•  ', '', 1))
            y += h

        if prix:
            if prix_barre:
                fonte_barre = police('Inter-SemiBold', 34 * unite)
                dessin.text((marge, y), prix_barre, font=fonte_barre, fill=doux)
                poses.append(prix_barre)
                largeur_barre = dessin.textlength(prix_barre, font=fonte_barre)
                milieu = y + fonte_barre.size * 0.62
                dessin.line([(marge, milieu), (marge + largeur_barre, milieu)], fill=doux, width=max(2, int(3 * unite)))
                y += int(40 * unite)
            etiquette = teinte_de('prix', accent)
            fonte_prix, _ = lignes_qui_tiennent(dessin, prix, 'InterDisplay-Black', taille_prix, utile - int(60 * unite), 1, 30 * unite)
            largeur_prix = dessin.textlength(prix, font=fonte_prix)
            hauteur_etiquette = int(fonte_prix.size * 1.45)
            dessin.rounded_rectangle([marge, y, marge + largeur_prix + int(56 * unite), y + hauteur_etiquette], int(hauteur_etiquette / 2), fill=etiquette)
            sombre = sum(etiquette) / 3 > 150
            dessin.text((marge + int(28 * unite), y + (hauteur_etiquette - fonte_prix.size) / 2 - fonte_prix.size * 0.08), prix, font=fonte_prix, fill=(20, 18, 15) if sombre else blanc)
            poses.append(prix)
            y += hauteur_etiquette + int(26 * unite)

        if lignes_pied:
            dessin.line([(marge, y), (largeur - marge, y)], fill=(255, 255, 255, 90), width=max(1, int(2 * unite)))
            y += int(22 * unite)
            for fonte_ligne, ligne, teinte in lignes_pied:
                dessin.text((marge, y), ligne, font=fonte_ligne, fill=teinte)
                y += int(fonte_ligne.size * 1.45)
            poses.append(' '.join(ligne for _, ligne, _ in lignes_pied))

    # Le logo du client, en haut à gauche, sur une carte blanche (jamais déformé).
    if logo and os.path.exists(logo):
        marque = Image.open(logo).convert('RGBA')
        cote = int(min(largeur, hauteur) * 0.17)
        marque.thumbnail((cote, cote), Image.LANCZOS)
        carte = Image.new('RGBA', (marque.width + int(28 * unite), marque.height + int(28 * unite)), (255, 255, 255, 240))
        carte.paste(marque, ((carte.width - marque.width) // 2, (carte.height - marque.height) // 2), marque)
        carte = coins_arrondis(carte, int(18 * unite))
        image.paste(carte, (marge, marge), carte)

    os.makedirs(dossier, exist_ok=True)
    fichiers = {'png': 'image.png'}
    image.save(os.path.join(dossier, 'image.png'), optimize=True)
    if format_ in IMPRESSION:
        # Pour imprimer : un PDF à la vraie taille (A4 ou A3), à 200 points par pouce.
        image.save(os.path.join(dossier, 'image.pdf'), 'PDF', resolution=IMPRESSION[format_])
        fichiers['pdf'] = 'image.pdf'
    apercu = image.copy()
    apercu.thumbnail((1080, 1080) if format_ not in ('vertical', 'a4', 'a3') else (900, 1600), Image.LANCZOS)
    apercu.save(os.path.join(dossier, 'apercu-1.jpg'), quality=86)
    print(json.dumps({'fichiers': fichiers, 'apercus': ['apercu-1.jpg'], 'pages': 1, 'taille': [largeur, hauteur], 'textes_poses': poses, 'avertissements': []}, ensure_ascii=False))


if __name__ == '__main__':
    if len(sys.argv) < 4 or sys.argv[1] != 'composer':
        raise SystemExit('usage : kekeli_images.py composer <structure.json> <dossier> [fond] [logo] [photo]')
    with open(sys.argv[2], encoding='utf-8') as f:
        donnees = json.load(f)
    arguments = [a if a and a != '-' else None for a in sys.argv[4:7]] + [None, None, None]
    composer(donnees, sys.argv[3], *arguments[:3])
