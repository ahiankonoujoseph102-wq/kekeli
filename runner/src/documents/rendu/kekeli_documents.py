#!/usr/bin/env python3
# Moteur de rendu des documents et présentations de Kekeli, copié dans un ordinateur E2B isolé (sans clé, sans
# Internet après l'installation). Il ne fait jamais confiance au contenu : la structure (JSON) écrite par le modèle est
# seulement mise en page, jamais exécutée.
#
#   python3 kekeli_documents.py rendre <structure.json> <dossier> <formats>   (formats : docx,pdf ou pptx,pdf)
#   python3 kekeli_documents.py extraire <fichier>                           (texte d'un .docx, .pptx ou .pdf)
#   python3 kekeli_documents.py corriger <fichier> <remplacements.json> <sortie>  (garde le format d'origine)
#
# Bibliothèques : python-docx (licence MIT), python-pptx (licence MIT), pypdf (licence BSD) ; LibreOffice (MPL 2.0)
# pour le PDF et Poppler (pdftoppm, GPL 2, programme séparé) pour les images d'aperçu.

import json
import os
import re
import subprocess
import sys

COULEUR_DEFAUT = '1F3A5F'
POLICE = 'Calibri'
POLICE_TITRES = 'Calibri'
PAGES_APERCU_MAX = 40


def couleur(valeur, defaut=COULEUR_DEFAUT):
    texte = str(valeur or '').strip().lstrip('#')
    return texte.upper() if re.fullmatch(r'[0-9A-Fa-f]{6}', texte) else defaut


def rgb(hexa):
    return tuple(int(hexa[i:i + 2], 16) for i in (0, 2, 4))


def texte(valeur, maximum=4000):
    return re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f]', '', str(valeur if valeur is not None else ''))[:maximum]


def morceaux(ligne):
    """Gras (**…**) et italique (*…*) simples : le reste est du texte ordinaire, jamais interprété."""
    parties = re.split(r'(\*\*[^*]+\*\*|\*[^*\s][^*]*\*)', ligne)
    for partie in parties:
        if not partie:
            continue
        if partie.startswith('**') and partie.endswith('**') and len(partie) > 4:
            yield partie[2:-2], True, False
        elif partie.startswith('*') and partie.endswith('*') and len(partie) > 2:
            yield partie[1:-1], False, True
        else:
            yield partie, False, False


def nombre(cellule):
    return bool(re.fullmatch(r'[-+]?[\d\s  .,]+\s*(FCFA|F CFA|XOF|€|\$|%)?', str(cellule).strip())) and any(c.isdigit() for c in str(cellule))


# --------------------------------------------------------------------------------------------------------------------
# Documents Word
# --------------------------------------------------------------------------------------------------------------------

def rendre_document(structure, chemin):
    from docx import Document
    from docx.enum.section import WD_ORIENT  # noqa: F401
    from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
    from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from docx.shared import Cm, Pt, RGBColor

    accent = couleur((structure.get('theme') or {}).get('couleur'))
    encre = '1C1A17'
    options = structure.get('options') or {}
    doc = Document()

    section = doc.sections[0]
    section.page_height, section.page_width = Cm(29.7), Cm(21.0)
    section.top_margin = section.bottom_margin = Cm(2.3)
    section.left_margin = section.right_margin = Cm(2.4)

    def langue(style_ou_run):
        rpr = style_ou_run.element.get_or_add_rPr() if hasattr(style_ou_run, 'element') else None
        if rpr is None:
            return
        lang = OxmlElement('w:lang')
        lang.set(qn('w:val'), str(structure.get('langue') or 'fr-FR'))
        rpr.append(lang)

    normal = doc.styles['Normal']
    normal.font.name = POLICE
    normal.font.size = Pt(11)
    normal.font.color.rgb = RGBColor(*rgb(encre))
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.15
    normal.element.rPr.rFonts.set(qn('w:eastAsia'), POLICE)
    langue(normal)
    for nom, taille, couleur_titre, avant in (('Heading 1', 16, accent, 18), ('Heading 2', 13, accent, 12), ('Heading 3', 11.5, encre, 10)):
        style = doc.styles[nom]
        style.font.name = POLICE_TITRES
        style.font.size = Pt(taille)
        style.font.bold = True
        style.font.italic = False
        style.font.color.rgb = RGBColor(*rgb(couleur_titre))
        style.paragraph_format.space_before = Pt(avant)
        style.paragraph_format.space_after = Pt(6)
        style.paragraph_format.keep_with_next = True
        rfonts = style.element.rPr.find(qn('w:rFonts'))
        if rfonts is not None:
            for attribut in ('w:asciiTheme', 'w:hAnsiTheme', 'w:eastAsiaTheme', 'w:cstheme'):
                rfonts.attrib.pop(qn(attribut), None)
            rfonts.set(qn('w:ascii'), POLICE_TITRES)
            rfonts.set(qn('w:hAnsi'), POLICE_TITRES)
    titre_style = doc.styles['Title']
    titre_style.font.name = POLICE_TITRES
    titre_style.font.size = Pt(24)
    titre_style.font.bold = True
    titre_style.font.color.rgb = RGBColor(*rgb(accent))
    titre_rfonts = titre_style.element.rPr.find(qn('w:rFonts'))
    if titre_rfonts is not None:
        for attribut in ('w:asciiTheme', 'w:hAnsiTheme', 'w:eastAsiaTheme', 'w:cstheme'):
            titre_rfonts.attrib.pop(qn(attribut), None)
    # Le titre du modèle Word a un trait bleu en dessous : retiré, la couleur du thème suffit.
    ppr = titre_style.element.find(qn('w:pPr'))
    if ppr is not None:
        for bordure in ppr.findall(qn('w:pBdr')):
            ppr.remove(bordure)

    def ecrire(paragraphe, ligne, gras=False, taille=None, couleur_texte=None):
        for morceau, est_gras, est_italique in morceaux(texte(ligne)):
            run = paragraphe.add_run(morceau)
            run.bold = gras or est_gras
            run.italic = est_italique
            if taille:
                run.font.size = Pt(taille)
            if couleur_texte:
                run.font.color.rgb = RGBColor(*rgb(couleur_texte))
        return paragraphe

    def champ(paragraphe, code):
        run = paragraphe.add_run()
        debut = OxmlElement('w:fldChar')
        debut.set(qn('w:fldCharType'), 'begin')
        instruction = OxmlElement('w:instrText')
        instruction.set(qn('xml:space'), 'preserve')
        instruction.text = code
        separe = OxmlElement('w:fldChar')
        separe.set(qn('w:fldCharType'), 'separate')
        valeur = OxmlElement('w:t')
        valeur.text = '1'
        fin = OxmlElement('w:fldChar')
        fin.set(qn('w:fldCharType'), 'end')
        for element in (debut, instruction, separe, valeur, fin):
            run._r.append(element)

    def fond(cellule, hexa):
        tcpr = cellule._tc.get_or_add_tcPr()
        ombre = OxmlElement('w:shd')
        ombre.set(qn('w:val'), 'clear')
        ombre.set(qn('w:color'), 'auto')
        ombre.set(qn('w:fill'), hexa)
        tcpr.append(ombre)

    def bordures(tableau, hexa='D9D4CC'):
        tblpr = tableau._tbl.tblPr
        cadre = OxmlElement('w:tblBorders')
        for cote in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
            trait = OxmlElement(f'w:{cote}')
            trait.set(qn('w:val'), 'single')
            trait.set(qn('w:sz'), '4')
            trait.set(qn('w:space'), '0')
            trait.set(qn('w:color'), hexa)
            cadre.append(trait)
        tblpr.append(cadre)

    # Numéros de page en bas, à droite : « 2 / 6 ».
    if options.get('numeros_page', True):
        pied = section.footer.paragraphs[0]
        pied.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        champ(pied, 'PAGE')
        pied.add_run(' / ')
        champ(pied, 'NUMPAGES')
        for run in pied.runs:
            run.font.size = Pt(9)
            run.font.color.rgb = RGBColor(0x6B, 0x66, 0x5E)

    # En-tête avec un logo (reçus de Kekeli) : le logo à gauche, quelques lignes à droite, puis un filet ambré. Le logo est
    # un fichier PNG déposé à côté du document par le Runner ; seul son nom est lu, jamais un chemin.
    logo = os.path.basename(str(structure.get('logo') or ''))
    chemin_logo = os.path.join(os.path.dirname(os.path.abspath(chemin)), logo) if logo else ''
    if logo.endswith('.png') and os.path.isfile(chemin_logo):
        haut = doc.add_table(rows=1, cols=2)
        haut.autofit = False
        gauche, droite = haut.rows[0].cells
        gauche.width = droite.width = Cm(8.1)
        gauche.vertical_alignment = droite.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        gauche.paragraphs[0].add_run().add_picture(chemin_logo, width=Cm(4.2))
        for n, ligne in enumerate((structure.get('en_tete_droite') or [])[:4]):
            p = droite.paragraphs[0] if n == 0 else droite.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            p.paragraph_format.space_after = Pt(0)
            ecrire(p, ligne, gras=n == 0, taille=10 if n == 0 else 9, couleur_texte='9A5B06' if n == 0 else '6B665E')
        filet = doc.add_paragraph()
        filet.paragraph_format.space_after = Pt(14)
        pbdr = OxmlElement('w:pBdr')
        bas = OxmlElement('w:bottom')
        for cle, valeur in (('w:val', 'single'), ('w:sz', '12'), ('w:space', '1'), ('w:color', couleur((structure.get('theme') or {}).get('filet'), 'E3A008'))):
            bas.set(qn(cle), valeur)
        pbdr.append(bas)
        filet._p.get_or_add_pPr().append(pbdr)

    blocs = [b for b in (structure.get('blocs') or []) if isinstance(b, dict)]
    titre = texte(structure.get('titre'), 200)
    sous_titre = texte(structure.get('sous_titre'), 300)
    a_un_en_tete = any(b.get('type') in ('en_tete', 'lettre') for b in blocs)

    if options.get('page_de_titre') and titre:
        for _ in range(8):
            doc.add_paragraph()
        p = doc.add_paragraph(style='Title')
        ecrire(p, titre)
        if sous_titre:
            ecrire(doc.add_paragraph(), sous_titre, taille=14, couleur_texte='4A453E')
        for ligne in (structure.get('auteur'), structure.get('date')):
            if ligne:
                ecrire(doc.add_paragraph(), ligne, couleur_texte='6B665E')
        doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)
    elif titre and not a_un_en_tete:
        ecrire(doc.add_paragraph(style='Title'), titre, couleur_texte=couleur((structure.get('theme') or {}).get('couleur_titre'), '') or None)
        if sous_titre:
            ecrire(doc.add_paragraph(), sous_titre, taille=13, couleur_texte='4A453E')

    # Sommaire des longs documents : les titres de niveau 1 et 2, sans numéros de page (toujours justes).
    if options.get('sommaire'):
        ecrire(doc.add_paragraph(style='Heading 1'), 'Sommaire', gras=True)
        for bloc in blocs:
            if bloc.get('type') in ('titre1', 'titre2'):
                p = doc.add_paragraph()
                p.paragraph_format.left_indent = Cm(0 if bloc['type'] == 'titre1' else 0.8)
                p.paragraph_format.space_after = Pt(2)
                ecrire(p, bloc.get('texte'), gras=bloc['type'] == 'titre1')
        doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)

    for bloc in blocs:
        genre = bloc.get('type')
        couleur_bloc = couleur(bloc.get('couleur'), '') or None
        if genre in ('titre1', 'titre2', 'titre3'):
            ecrire(doc.add_paragraph(style={'titre1': 'Heading 1', 'titre2': 'Heading 2', 'titre3': 'Heading 3'}[genre]), bloc.get('texte'), gras=True, couleur_texte=couleur_bloc)
        elif genre == 'paragraphe':
            p = ecrire(doc.add_paragraph(), bloc.get('texte'), couleur_texte=couleur_bloc)
            if bloc.get('alignement') == 'droite':
                p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            elif bloc.get('alignement') == 'centre':
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            elif bloc.get('alignement') == 'justifie':
                p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        elif genre == 'liste':
            style = 'List Number' if bloc.get('numerotee') else 'List Bullet'
            for element in (bloc.get('elements') or [])[:60]:
                p = ecrire(doc.add_paragraph(style=style), element)
                p.paragraph_format.space_after = Pt(3)
        elif genre == 'citation':
            p = ecrire(doc.add_paragraph(), bloc.get('texte'), couleur_texte='4A453E')
            p.paragraph_format.left_indent = Cm(1)
            for run in p.runs:
                run.italic = True
        elif genre == 'tableau':
            entetes = [texte(c, 200) for c in (bloc.get('entetes') or [])][:8]
            lignes = [[texte(c, 400) for c in (ligne or [])][:len(entetes) or 8] for ligne in (bloc.get('lignes') or [])][:80]
            colonnes = len(entetes) or max((len(l) for l in lignes), default=0)
            if not colonnes:
                continue
            if bloc.get('titre'):
                ecrire(doc.add_paragraph(), bloc.get('titre'), gras=True)
            tableau = doc.add_table(rows=0, cols=colonnes)
            tableau.alignment = WD_TABLE_ALIGNMENT.CENTER
            bordures(tableau)
            if entetes:
                cellules = tableau.add_row().cells
                for i, valeur in enumerate(entetes):
                    fond(cellules[i], accent)
                    p = cellules[i].paragraphs[0]
                    ecrire(p, valeur, gras=True, couleur_texte='FFFFFF')
                    p.paragraph_format.space_after = Pt(2)
                    if lignes and i < len(lignes[0]) and nombre(lignes[0][i]):
                        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
                # La ligne de titres se répète en haut de chaque page.
                trpr = tableau.rows[0]._tr.get_or_add_trPr()
                repete = OxmlElement('w:tblHeader')
                repete.set(qn('w:val'), 'true')
                trpr.append(repete)
            # Largeur de chaque colonne selon la longueur de son contenu (la première colonne garde de la place).
            longueurs = [max([len(entetes[i]) if i < len(entetes) else 0] + [len(l[i]) if i < len(l) else 0 for l in lignes] + [4]) for i in range(colonnes)]
            poids = [min(40, l) + 6 for l in longueurs]
            tableau.autofit = False
            largeur_totale = 16.2
            total = bool(bloc.get('derniere_ligne_total'))
            for n, ligne in enumerate(lignes):
                cellules = tableau.add_row().cells
                derniere = total and n == len(lignes) - 1
                for i in range(colonnes):
                    valeur = ligne[i] if i < len(ligne) else ''
                    if derniere:
                        fond(cellules[i], couleur((structure.get('theme') or {}).get('fond_total'), 'EFE9DF'))
                    elif n % 2 == 1:
                        fond(cellules[i], 'F7F4EF')
                    p = cellules[i].paragraphs[0]
                    ecrire(p, valeur, gras=derniere)
                    p.paragraph_format.space_after = Pt(2)
                    if nombre(valeur):
                        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            for i, colonne in enumerate(tableau.columns):
                colonne.width = Cm(largeur_totale * poids[i] / sum(poids))
            for ligne in tableau.rows:
                for i, cellule in enumerate(ligne.cells):
                    cellule.width = Cm(largeur_totale * poids[i] / sum(poids))
            doc.add_paragraph()
        elif genre == 'saut_de_page':
            # Les sauts de page du modèle laissaient des pages presque vides : le texte s'enchaîne naturellement.
            continue
        elif genre == 'en_tete':
            # En-tête d'un CV : le nom en grand, la fonction, puis les coordonnées.
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            ecrire(p, bloc.get('nom'), gras=True, taille=24, couleur_texte=accent)
            p.paragraph_format.space_after = Pt(0)
            if bloc.get('fonction'):
                ecrire(doc.add_paragraph(), bloc.get('fonction'), taille=13, couleur_texte='4A453E').paragraph_format.space_after = Pt(2)
            coordonnees = [texte(c, 200) for c in (bloc.get('coordonnees') or []) if c][:6]
            if coordonnees:
                ecrire(doc.add_paragraph(), '  ·  '.join(coordonnees), taille=10, couleur_texte='6B665E')
            trait = doc.add_paragraph()
            pbdr = OxmlElement('w:pBdr')
            bas = OxmlElement('w:bottom')
            bas.set(qn('w:val'), 'single')
            bas.set(qn('w:sz'), '8')
            bas.set(qn('w:space'), '1')
            bas.set(qn('w:color'), accent)
            pbdr.append(bas)
            trait._p.get_or_add_pPr().append(pbdr)
        elif genre == 'lettre':
            # Lettre : l'expéditeur à gauche, le destinataire à droite, puis le lieu et la date, puis l'objet.
            for ligne in (bloc.get('expediteur') or [])[:8]:
                ecrire(doc.add_paragraph(), ligne).paragraph_format.space_after = Pt(0)
            doc.add_paragraph()
            for ligne in (bloc.get('destinataire') or [])[:8]:
                p = ecrire(doc.add_paragraph(), ligne)
                p.paragraph_format.left_indent = Cm(9)
                p.paragraph_format.space_after = Pt(0)
            doc.add_paragraph()
            if bloc.get('lieu_date'):
                p = ecrire(doc.add_paragraph(), bloc.get('lieu_date'))
                p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            if bloc.get('objet'):
                p = doc.add_paragraph()
                couleur_objet = couleur(bloc.get('couleur_objet'), '') or None
                ecrire(p, 'Objet : ', gras=True, couleur_texte=couleur_objet)
                ecrire(p, bloc.get('objet'), gras=True, couleur_texte=couleur_objet)
                p.paragraph_format.space_before = Pt(12)
                p.paragraph_format.space_after = Pt(12)
        elif genre == 'signature':
            doc.add_paragraph()
            for ligne in (bloc.get('lignes') or [])[:6]:
                p = ecrire(doc.add_paragraph(), ligne)
                p.paragraph_format.left_indent = Cm(9)
                p.paragraph_format.space_after = Pt(0)
        elif genre == 'sources':
            elements = [e for e in (bloc.get('elements') or []) if isinstance(e, dict)][:10]
            if elements:
                ecrire(doc.add_paragraph(style='Heading 2'), 'Sources', gras=True)
                for n, source in enumerate(elements, 1):
                    p = doc.add_paragraph()
                    ecrire(p, f"{n}. {texte(source.get('titre'), 200)}", taille=9.5)
                    if source.get('url'):
                        ecrire(p, ' — ' + texte(source.get('url'), 300), taille=9.5, couleur_texte='6B665E')
                    p.paragraph_format.space_after = Pt(2)

    doc.core_properties.title = titre
    doc.core_properties.author = texte(structure.get('auteur') or 'Kekeli', 120)
    doc.save(chemin)


# --------------------------------------------------------------------------------------------------------------------
# Présentations PowerPoint
# --------------------------------------------------------------------------------------------------------------------

THEMES = {
    # fond, texte, texte doux, accent, fond des diapositives de titre et de section, accent sur ce fond foncé
    'sobre': ('FFFFFF', '1C1A17', '5F5A52', '1F3A5F', '1F3A5F', 'E3B341'),
    'lumiere': ('FAF8F4', '1C1A17', '5F5A52', 'B7791F', '1C1A17', 'E3A008'),
    'ocean': ('0F2A3D', 'FFFFFF', 'B9CCD8', '4FB3BF', '0B1F2E', '4FB3BF'),
    'terre': ('F6F0E6', '2E2219', '6B5847', 'B5532D', '5A3A24', 'E8A27C'),
    'foret': ('F3F6F2', '17251C', '4E5F53', '2F6B45', '17251C', '8CC79F'),
}


def taille_qui_tient(textes, largeur_pouces, hauteur_pouces, depart, minimum, interligne=1.25, espace_points=0.6):
    """La plus grande taille de police (en points) pour que les textes tiennent dans la boîte, sans rien couper."""
    taille = depart
    while taille > minimum:
        caracteres_par_ligne = max(8, int(largeur_pouces * 72 / (taille * 0.52)))
        lignes = 0
        for t in textes:
            mots, longueur, n = str(t).split(), 0, 1
            for mot in mots:
                if longueur + len(mot) + 1 > caracteres_par_ligne and longueur:
                    n += 1
                    longueur = len(mot)
                else:
                    longueur += len(mot) + 1
            lignes += n
        hauteur = (lignes * taille * interligne + len(textes) * taille * espace_points) / 72
        if hauteur <= hauteur_pouces:
            return taille
        taille -= 1
    return minimum


def rendre_presentation(structure, chemin, avertissements):
    from pptx import Presentation
    from pptx.dml.color import RGBColor
    from pptx.enum.shapes import MSO_SHAPE
    from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
    from pptx.util import Inches, Pt

    nom_theme = str(structure.get('theme') or 'sobre')
    fond, encre, doux, accent, fond_section, accent_sombre = THEMES.get(nom_theme, THEMES['sobre'])
    perso = couleur((structure.get('couleur') or ''), '')
    if perso:
        accent = perso
    prs = Presentation()
    prs.slide_width, prs.slide_height = Inches(13.333), Inches(7.5)
    vide = prs.slide_layouts[6]
    titre_general = texte(structure.get('titre'), 120)
    diapositives = [d for d in (structure.get('diapositives') or []) if isinstance(d, dict)][:40]

    def rect(diapo, x, y, l, h, hexa):
        forme = diapo.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(x), Inches(y), Inches(l), Inches(h))
        forme.fill.solid()
        forme.fill.fore_color.rgb = RGBColor(*rgb(hexa))
        forme.line.fill.background()
        # Aucune ombre : le style par défaut des formes en ajoute une.
        style = forme._element.find('{http://schemas.openxmlformats.org/presentationml/2006/main}style')
        if style is not None:
            forme._element.remove(style)
        return forme

    def boite(diapo, x, y, l, h, lignes, taille, hexa, gras=False, alignement=PP_ALIGN.LEFT, ancre=MSO_ANCHOR.TOP, puces=False, espace=8):
        cadre = diapo.shapes.add_textbox(Inches(x), Inches(y), Inches(l), Inches(h)).text_frame
        cadre.word_wrap = True
        cadre.vertical_anchor = ancre
        cadre.margin_left = cadre.margin_right = Inches(0.05)
        for i, ligne in enumerate(lignes):
            p = cadre.paragraphs[0] if i == 0 else cadre.add_paragraph()
            p.alignment = alignement
            p.space_after = Pt(espace)
            if puces:
                run = p.add_run()
                run.text = '■  '
                run.font.size = Pt(max(10, taille * 0.55))
                run.font.color.rgb = RGBColor(*rgb(accent))
            for morceau, est_gras, est_italique in morceaux(texte(ligne, 400)):
                run = p.add_run()
                run.text = morceau
                run.font.size = Pt(taille)
                run.font.bold = gras or est_gras
                run.font.italic = est_italique
                run.font.name = POLICE
                run.font.color.rgb = RGBColor(*rgb(hexa))
        return cadre

    def pied(diapo, numero, sur_fond):
        couleur_pied = 'FFFFFF' if sur_fond else doux
        if titre_general:
            boite(diapo, 0.6, 6.95, 9, 0.35, [titre_general], 10, couleur_pied)
        boite(diapo, 11.7, 6.95, 1.0, 0.35, [str(numero)], 10, couleur_pied, alignement=PP_ALIGN.RIGHT)

    def titre_haut(diapo, valeur):
        t = texte(valeur, 160)
        taille = taille_qui_tient([t], 12.1, 1.0, 32, 22)
        boite(diapo, 0.6, 0.45, 12.1, 1.1, [t], taille, encre, gras=True, ancre=MSO_ANCHOR.MIDDLE)
        rect(diapo, 0.62, 1.55, 0.9, 0.06, accent)

    def verifier(numero, textes, largeur, hauteur, depart, minimum):
        taille = taille_qui_tient(textes, largeur, hauteur, depart, minimum)
        if taille <= minimum and taille_qui_tient(textes, largeur, hauteur, minimum, minimum - 4) < minimum:
            avertissements.append(f'Diapositive {numero} : texte très long, réduit au minimum.')
        return taille

    for numero, d in enumerate(diapositives, 1):
        diapo = prs.slides.add_slide(vide)
        mise = d.get('mise_en_page') or 'points'
        sombre = mise in ('titre', 'section', 'fin')
        rect(diapo, 0, 0, 13.333, 7.5, fond_section if sombre else fond)
        if mise in ('titre', 'fin'):
            rect(diapo, 0.6, 2.35, 1.2, 0.08, accent_sombre)
            t = texte(d.get('titre'), 160)
            taille = taille_qui_tient([t], 11.5, 2.0, 46, 28)
            boite(diapo, 0.6, 2.6, 11.5, 2.1, [t], taille, 'FFFFFF', gras=True, ancre=MSO_ANCHOR.TOP)
            if d.get('sous_titre'):
                boite(diapo, 0.6, 4.8, 11.5, 1.4, [texte(d.get('sous_titre'), 300)], 20, 'E8E4DC')
        elif mise == 'section':
            boite(diapo, 0.6, 2.4, 11.5, 1.0, [texte(d.get('numero') or '', 10)], 22, accent_sombre, gras=True)
            boite(diapo, 0.6, 3.1, 11.5, 1.6, [texte(d.get('titre'), 160)], 38, 'FFFFFF', gras=True)
            if d.get('sous_titre'):
                boite(diapo, 0.6, 4.7, 11.5, 1.2, [texte(d.get('sous_titre'), 300)], 18, 'E8E4DC')
        elif mise == 'deux_colonnes':
            titre_haut(diapo, d.get('titre'))
            for i, cote in enumerate(('gauche', 'droite')):
                bloc = d.get(cote) or {}
                x = 0.6 + i * 6.25
                rect(diapo, x, 2.0, 5.85, 4.6, 'F2EEE7' if fond in ('FFFFFF', 'FAF8F4', 'F6F0E6', 'F3F6F2') else '173A52')
                boite(diapo, x + 0.3, 2.2, 5.3, 0.7, [texte(bloc.get('titre'), 80)], 20, accent, gras=True)
                points = [texte(p, 200) for p in (bloc.get('points') or [])][:5]
                taille = verifier(numero, points, 5.2, 3.5, 20, 13)
                boite(diapo, x + 0.3, 2.95, 5.3, 3.5, points, taille, encre if fond != THEMES['ocean'][0] else 'FFFFFF', puces=True)
        elif mise == 'chiffres':
            titre_haut(diapo, d.get('titre'))
            chiffres = [c for c in (d.get('chiffres') or []) if isinstance(c, dict)][:4]
            n = max(1, len(chiffres))
            largeur = (12.1 - (n - 1) * 0.3) / n
            for i, c in enumerate(chiffres):
                x = 0.6 + i * (largeur + 0.3)
                rect(diapo, x, 2.3, largeur, 3.6, 'F2EEE7' if fond in ('FFFFFF', 'FAF8F4', 'F6F0E6', 'F3F6F2') else '173A52')
                valeur = texte(c.get('valeur'), 20)
                taille = taille_qui_tient([valeur], largeur - 0.4, 1.3, 48, 24)
                boite(diapo, x + 0.2, 2.6, largeur - 0.4, 1.4, [valeur], taille, accent, gras=True, alignement=PP_ALIGN.CENTER, ancre=MSO_ANCHOR.MIDDLE)
                libelle = texte(c.get('libelle'), 120)
                taille = taille_qui_tient([libelle], largeur - 0.4, 1.6, 18, 12)
                boite(diapo, x + 0.2, 4.1, largeur - 0.4, 1.6, [libelle], taille, encre if fond != THEMES['ocean'][0] else 'FFFFFF', alignement=PP_ALIGN.CENTER)
        elif mise == 'citation':
            rect(diapo, 0.6, 1.4, 0.12, 3.6, accent)
            t = texte(d.get('texte'), 400)
            taille = taille_qui_tient([t], 11.0, 3.6, 34, 20)
            boite(diapo, 1.1, 1.4, 11.0, 3.6, [f'« {t} »'], taille, encre, ancre=MSO_ANCHOR.MIDDLE)
            if d.get('auteur'):
                boite(diapo, 1.1, 5.2, 11.0, 0.8, [texte(d.get('auteur'), 120)], 18, doux)
        elif mise == 'tableau':
            titre_haut(diapo, d.get('titre'))
            entetes = [texte(c, 80) for c in (d.get('entetes') or [])][:6]
            lignes = [[texte(c, 120) for c in (l or [])][:len(entetes) or 6] for l in (d.get('lignes') or [])][:8]
            colonnes = len(entetes) or max((len(l) for l in lignes), default=1)
            hauteur_ligne = min(0.75, 4.6 / max(1, len(lignes) + 1))
            forme = diapo.shapes.add_table(len(lignes) + 1, colonnes, Inches(0.6), Inches(2.0), Inches(12.1), Inches(hauteur_ligne * (len(lignes) + 1)))
            tableau = forme.table
            taille = 22 if len(lignes) <= 4 else 18 if len(lignes) <= 6 else 14
            for i in range(colonnes):
                cellule = tableau.cell(0, i)
                cellule.fill.solid()
                cellule.fill.fore_color.rgb = RGBColor(*rgb(accent))
                cellule.text = entetes[i] if i < len(entetes) else ''
                for p in cellule.text_frame.paragraphs:
                    for r in p.runs:
                        r.font.size, r.font.bold, r.font.name = Pt(taille), True, POLICE
                        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
            for n, ligne in enumerate(lignes, 1):
                for i in range(colonnes):
                    cellule = tableau.cell(n, i)
                    cellule.fill.solid()
                    cellule.fill.fore_color.rgb = RGBColor(*rgb('F7F4EF' if n % 2 == 0 else 'FFFFFF'))
                    valeur = ligne[i] if i < len(ligne) else ''
                    cellule.text = valeur
                    for p in cellule.text_frame.paragraphs:
                        if nombre(valeur):
                            p.alignment = PP_ALIGN.RIGHT
                        for r in p.runs:
                            r.font.size, r.font.name = Pt(taille), POLICE
                            r.font.color.rgb = RGBColor(*rgb('1C1A17'))
        else:
            titre_haut(diapo, d.get('titre'))
            points = [texte(p, 220) for p in (d.get('points') or [])][:6]
            taille = verifier(numero, points, 11.6, 4.7, 32 if len(points) <= 3 else 28, 16)
            boite(diapo, 0.7, 2.0, 11.6, 4.7, points, taille, encre, puces=True, espace=14)
        if d.get('notes'):
            diapo.notes_slide.notes_text_frame.text = texte(d.get('notes'), 2000)
        if mise not in ('titre',):
            pied(diapo, numero, sombre)
    prs.core_properties.title = titre_general
    prs.save(chemin)


# --------------------------------------------------------------------------------------------------------------------
# PDF et images d'aperçu
# --------------------------------------------------------------------------------------------------------------------

def vers_pdf(source, dossier):
    profil = '/tmp/kekeli-profil-libreoffice'
    subprocess.run(['soffice', f'-env:UserInstallation=file://{profil}', '--headless', '--convert-to', 'pdf', '--outdir', dossier, source],
                   check=True, capture_output=True, timeout=180)
    return os.path.join(dossier, os.path.splitext(os.path.basename(source))[0] + '.pdf')


def apercus(pdf, dossier, presentation):
    for ancien in os.listdir(dossier):
        if ancien.startswith('apercu-'):
            os.remove(os.path.join(dossier, ancien))
    resolution = '72' if presentation else '96'
    subprocess.run(['pdftoppm', '-jpeg', '-jpegopt', 'quality=82', '-r', resolution, '-l', str(PAGES_APERCU_MAX), pdf, os.path.join(dossier, 'apercu')],
                   check=True, capture_output=True, timeout=180)
    return sorted((f for f in os.listdir(dossier) if f.startswith('apercu-')), key=lambda f: int(re.findall(r'\d+', f)[-1]))


def pages_du_pdf(pdf):
    from pypdf import PdfReader
    return len(PdfReader(pdf).pages)


def commande_rendre(chemin_structure, dossier, formats):
    with open(chemin_structure, encoding='utf-8') as f:
        structure = json.load(f)
    os.makedirs(dossier, exist_ok=True)
    avertissements = []
    presentation = structure.get('type') == 'presentation'
    nom = 'presentation.pptx' if presentation else 'document.docx'
    source = os.path.join(dossier, nom)
    if presentation:
        rendre_presentation(structure, source, avertissements)
    else:
        rendre_document(structure, source)
    pdf = vers_pdf(source, dossier)
    images = apercus(pdf, dossier, presentation)
    voulus = set(formats.split(',')) if formats else set()
    fichiers = {nom.rsplit('.', 1)[1]: nom}
    if 'pdf' in voulus or not voulus:
        fichiers['pdf'] = os.path.basename(pdf)
    else:
        os.remove(pdf)
    print(json.dumps({'fichiers': fichiers, 'apercus': images, 'pages': pages_du_pdf(pdf) if os.path.exists(pdf) else len(images), 'avertissements': avertissements}, ensure_ascii=False))


# --------------------------------------------------------------------------------------------------------------------
# Fichiers joints par le client : lecture et correction en gardant le format
# --------------------------------------------------------------------------------------------------------------------

def paragraphes_docx(doc):
    for i, p in enumerate(doc.paragraphs):
        yield f'p{i}', p
    for t, tableau in enumerate(doc.tables):
        for r, ligne in enumerate(tableau.rows):
            for c, cellule in enumerate(ligne.cells):
                for i, p in enumerate(cellule.paragraphs):
                    yield f't{t}.{r}.{c}.{i}', p


def paragraphes_pptx(prs):
    for s, diapo in enumerate(prs.slides, 1):
        for forme in diapo.shapes:
            if forme.has_text_frame:
                for i, p in enumerate(forme.text_frame.paragraphs):
                    yield f's{s}.{forme.shape_id}.{i}', p
            if getattr(forme, 'has_table', False) and forme.has_table:
                for r, ligne in enumerate(forme.table.rows):
                    for c, cellule in enumerate(ligne.cells):
                        for i, p in enumerate(cellule.text_frame.paragraphs):
                            yield f's{s}.{forme.shape_id}.t{r}.{c}.{i}', p


def commande_extraire(chemin):
    extension = chemin.rsplit('.', 1)[-1].lower()
    morceaux_lus = []
    if extension == 'docx':
        from docx import Document
        for ident, p in paragraphes_docx(Document(chemin)):
            if p.text.strip():
                morceaux_lus.append({'id': ident, 'texte': p.text, 'style': p.style.name if p.style is not None else ''})
    elif extension == 'pptx':
        from pptx import Presentation
        for ident, p in paragraphes_pptx(Presentation(chemin)):
            valeur = ''.join(r.text for r in p.runs)
            if valeur.strip():
                morceaux_lus.append({'id': ident, 'texte': valeur})
    elif extension == 'pdf':
        from pypdf import PdfReader
        for n, page in enumerate(PdfReader(chemin).pages[:80], 1):
            morceaux_lus.append({'id': f'page{n}', 'texte': (page.extract_text() or '').strip()})
    else:
        raise SystemExit('format non pris en charge')
    print(json.dumps({'format': extension, 'morceaux': morceaux_lus}, ensure_ascii=False))


def remplacer(paragraphe, nouveau):
    """Le nouveau texte est réparti sur les morceaux d'origine (gras, italique, couleur gardés) : les limites entre
    morceaux suivent la correspondance entre l'ancien et le nouveau texte. Le style du paragraphe est gardé."""
    import difflib
    runs = [r for r in paragraphe.runs]
    if not runs:
        paragraphe.add_run(nouveau)
        return
    if len(runs) == 1:
        runs[0].text = nouveau
        return
    ancien = ''.join(r.text for r in runs)
    # Position dans le nouveau texte de chaque caractère de l'ancien.
    correspondance = [0] * (len(ancien) + 1)
    for code, a1, a2, b1, b2 in difflib.SequenceMatcher(None, ancien, nouveau, autojunk=False).get_opcodes():
        for i in range(a1, a2 + 1):
            correspondance[i] = b1 if code != 'equal' else b1 + (i - a1)
            if code != 'equal' and i == a2:
                correspondance[i] = b2
    correspondance[len(ancien)] = len(nouveau)
    debut = 0
    position = 0
    for n, run in enumerate(runs):
        position += len(run.text)
        fin = len(nouveau) if n == len(runs) - 1 else max(debut, min(len(nouveau), correspondance[position]))
        # Une limite ne coupe jamais un mot : elle avance jusqu'à l'espace suivante.
        while 0 < fin < len(nouveau) and nouveau[fin - 1] != ' ' and nouveau[fin] != ' ' and n < len(runs) - 1:
            fin += 1
        run.text = nouveau[debut:fin]
        debut = fin


def commande_corriger(chemin, chemin_remplacements, sortie):
    with open(chemin_remplacements, encoding='utf-8') as f:
        remplacements = {str(k): texte(v, 8000) for k, v in (json.load(f) or {}).items()}
    extension = chemin.rsplit('.', 1)[-1].lower()
    faits = 0
    if extension == 'docx':
        from docx import Document
        doc = Document(chemin)
        for ident, p in paragraphes_docx(doc):
            if ident in remplacements:
                remplacer(p, remplacements[ident])
                faits += 1
        doc.save(sortie)
    elif extension == 'pptx':
        from pptx import Presentation
        prs = Presentation(chemin)
        for ident, p in paragraphes_pptx(prs):
            if ident in remplacements:
                remplacer(p, remplacements[ident])
                faits += 1
        prs.save(sortie)
    else:
        raise SystemExit('format non pris en charge')
    dossier = os.path.dirname(sortie) or '.'
    pdf = vers_pdf(sortie, dossier)
    images = apercus(pdf, dossier, extension == 'pptx')
    print(json.dumps({'remplaces': faits, 'apercus': images, 'pdf': os.path.basename(pdf), 'pages': pages_du_pdf(pdf)}, ensure_ascii=False))


if __name__ == '__main__':
    if len(sys.argv) < 3:
        raise SystemExit(__doc__)
    commande = sys.argv[1]
    if commande == 'rendre':
        commande_rendre(sys.argv[2], sys.argv[3], sys.argv[4] if len(sys.argv) > 4 else '')
    elif commande == 'extraire':
        commande_extraire(sys.argv[2])
    elif commande == 'corriger':
        commande_corriger(sys.argv[2], sys.argv[3], sys.argv[4])
    else:
        raise SystemExit('commande inconnue')
