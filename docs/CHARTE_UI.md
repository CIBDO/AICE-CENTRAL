# Charte UI — Dashboard DGTCP

Document de référence pour l'interface Vue 3. Objectif : un rendu **institutionnel**, **sobre** et **lisible**, comparable aux tableaux de bord des grandes administrations financières — sans esthétique générique de template SaaS ni « touche IA ».

---

## 1. Principes directeurs

| Principe | Application |
|----------|-------------|
| **Données d'abord** | L'information comptable prime sur la décoration |
| **Sobriété** | Pas de gradients, illustrations marketing, badges flashy |
| **Cohérence** | Même grille, mêmes espacements, mêmes composants partout |
| **Lisibilité** | Contrastes suffisants, typographie stable, chiffres alignés |
| **Institutionnel** | Palette neutre + une couleur d'accent unique (bleu marine) |
| **Français** | Interface en français, formats locale `fr-FR` |

### Interdictions explicites

- Dégradés violet/rose/cyan (palette Vuexy par défaut)
- Cartes KPI avec icônes oversized colorées et ombres prononcées
- Animations excessives, micro-interactions « wow »
- Illustrations 3D, personnages, emojis dans l'UI métier
- Badges « New », compteurs décoratifs dans la navigation
- Dark mode par défaut (light mode institutionnel en v1)
- Pages demo du template (ecommerce, academy, logistics…) dans la nav prod

---

## 2. Palette

### Couleurs institutionnelles

| Token | Hex | Usage |
|-------|-----|-------|
| `primary` | `#1E3A5F` | En-têtes, liens actifs, barres graphiques principales |
| `primary-darken-1` | `#152A45` | Hover, texte sur fond clair accentué |
| `secondary` | `#4A5568` | Texte secondaire, labels |
| `background` | `#F4F5F7` | Fond de page |
| `surface` | `#FFFFFF` | Cartes, panneaux |
| `border` | `#E2E8F0` | Séparateurs, bordures de table |
| `on-surface` | `#1A202C` | Texte principal |
| `success` | `#276749` | Recettes, soldes positifs |
| `error` | `#C53030` | Dépenses, alertes |
| `warning` | `#B7791F` | Vigilance, retards |

Pas de couleur `info` criarde — utiliser `secondary` pour l'information neutre.

---

## 3. Typographie

| Élément | Style |
|---------|-------|
| Police | **Public Sans** ou **Roboto** (déjà dans le template) |
| Titres de page | 1.25rem, weight 600, couleur `on-surface` |
| Sous-titres | 0.875rem, weight 400, couleur `secondary` |
| KPI — valeur | 1.75rem, weight 600, tabular-nums |
| KPI — label | 0.75rem, uppercase, letter-spacing 0.04em, `secondary` |
| Tableaux | 0.8125rem, tabular-nums pour les montants |
| Montants | Toujours `font-variant-numeric: tabular-nums`, alignés à droite |

---

## 4. Grille et espacements

- **Largeur contenu** : pleine largeur (`ContentWidth.Fluid`), pas de boxed centré étroit
- **Padding page** : 24px desktop, 16px mobile
- **Gap entre sections** : 24px
- **Cartes KPI** : grille responsive `cols="12" sm="6" lg="3"`
- **Pas d'élévation** excessive : `elevation="0"` + bordure `1px solid border`

---

## 5. Composants standards

### Carte KPI (`KpiStat`)

```
┌─────────────────────────────┐
│ RECETTES                    │  ← label uppercase discret
│ 1 234 567 890 FCFA          │  ← valeur, grande, tabular
│ ▲ +4,2 % vs période prec.   │  ← variation optionnelle, petite
└─────────────────────────────┘
```

- Fond blanc, bordure fine, pas d'icône décorative obligatoire
- Variation en vert/rouge muted uniquement si pertinent

### Barre de filtres (`PeriodToolbar`)

- Sélecteur région (si autorisé)
- Année, mois, plage de dates
- Bouton « Actualiser » discret (outlined, pas de couleur vive)
- Alignement horizontal, fond `surface`, bordure basse

### Tableaux de données

- `VDataTableServer` avec en-têtes fond `#F8FAFC`
- Lignes zébrées très légères ou sans zébrage
- Pagination sobre en bas
- Pas de chips colorés sauf statuts métier (Payé, Admis, Rejeté)

### Graphiques

- **Chart.js** en priorité (courbes, barres simples)
- Maximum 3 couleurs par graphique
- Pas de fond transparent avec grille agressive
- Légende sous le graphique, pas flottante

---

## 6. Navigation

Structure sidebar verticale :

```
Tableau de bord
  └ Régional
  └ Central
  └ Exécutif
Données
  └ Mandats
  └ Recettes
  └ Banques
  └ Programmes
Administration (si autorisé)
  └ Utilisateurs
  └ Rôles
  └ Régions
```

- Icônes Tabler minimalistes (`tabler-chart-bar`, `tabler-file-text`…)
- Pas de badges numériques décoratifs
- Section active : barre latérale `primary`, fond légèrement teinté

---

## 7. En-tête application

```
[Logo DGTCP]  Dashboard de la Trésorerie     [Région: Kayes ▾]  [Utilisateur ▾]
```

- Fond blanc, ombre très légère ou bordure basse uniquement
- Titre institutionnel, pas le nom du template

---

## 8. Formulaires et actions

- Champs : `variant="outlined"`, `density="compact"`
- Bouton principal : `color="primary"`, `variant="flat"`, sans ombre
- Bouton secondaire : `variant="outlined"`, `color="secondary"`
- Pas de boutons dégradés

---

## 9. États vides et chargement

- **Chargement** : skeleton gris discret, pas de spinner coloré plein écran
- **Vide** : message texte simple + action suggérée, pas d'illustration SVG marketing
- **Erreur** : alerte `VAlert` `type="error"` `variant="tonal"`, texte explicite

---

## 10. Références visuelles (inspiration, pas copie)

- Portails OpenGov / dashboards trésor publics (UK GDS, France data.gouv style sobre)
- Rapports annuels institutionnels : hiérarchie typographique claire
- **Anti-références** : dashboards SaaS colorés, templates « AI analytics », Dribbble fintech neon

---

## 11. Fichiers de configuration

| Fichier | Rôle |
|---------|------|
| `themeConfig.ts` | Titre app, layout, locale FR par défaut |
| `plugins/vuetify/theme.ts` | Palette institutionnelle |
| `plugins/vuetify/defaults.ts` | Variants plats, outlined |
| `resources/styles/aice/` | Styles métier DGTCP |
| `components/aice/` | Composants réutilisables métier |

---

*Version 1.0 — juin 2026*
