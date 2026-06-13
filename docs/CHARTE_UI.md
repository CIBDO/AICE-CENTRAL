# Charte UI — Dashboard DGTCP

Document de référence pour l'interface Vue 3. Objectif : un rendu **institutionnel**, **sobre** et **lisible**, aux couleurs officielles de la DGTCP (République du Mali).

---

## 1. Principes directeurs

| Principe | Application |
|----------|-------------|
| **Données d'abord** | L'information comptable prime sur la décoration |
| **Sobriété** | Pas d'illustrations marketing, badges flashy |
| **Cohérence** | Même grille, mêmes espacements, mêmes composants partout |
| **Lisibilité** | Contrastes suffisants, typographie stable, chiffres alignés |
| **Institutionnel** | Vert DGTCP + accents Or et Rouge République |
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

## 2. Palette officielle DGTCP

### Couleurs principales

| Token | Hex | RGB | Usage |
|-------|-----|-----|-------|
| **Vert institutionnel** | `#08A04B` | 8, 160, 75 | Barre latérale, menus, boutons primaires, fonds institutionnels, hero dashboards |
| **Jaune Or** | `#E7C936` | 231, 201, 54 | Accents, badges, statistiques importantes (encaisse), barre active sidebar |
| **Rouge République** | `#E53935` | 229, 57, 53 | Alertes, notifications critiques, suppressions, dépenses |
| **Noir institutionnel** | `#000000` | 0, 0, 0 | Titres, textes importants |

### Couleurs secondaires

| Token | Hex | Usage |
|-------|-----|-------|
| **Blanc** | `#FFFFFF` | Cartes, formulaires, tableaux |
| **Gris clair** | `#F5F7FA` | Fond général de l'application |
| **Gris moyen** | `#DDE3EA` | Bordures, séparateurs, cartes inactives |
| **Gris foncé** | `#374151` | Texte secondaire, libellés |

### Mapping Vuetify

| Token Vuetify | Hex | Rôle |
|---------------|-----|------|
| `primary` | `#08A04B` | Actions principales, liens actifs, graphiques |
| `primary-darken-1` | `#067A39` | Hover, dégradés hero |
| `secondary` | `#374151` | Texte secondaire, info neutre |
| `warning` | `#E7C936` | Accents Or, KPI encaisse |
| `error` | `#E53935` | Alertes, dépenses, suppressions |
| `success` | `#08A04B` | Recettes, soldes positifs |
| `background` | `#F5F7FA` | Fond de page |
| `surface` | `#FFFFFF` | Cartes, panneaux |
| `on-surface` | `#000000` | Texte principal |

---

## 3. Logo

- Fichier source : `DGTCP.png` (racine projet)
- Déployé dans : `public/images/dgtcp-logo.png`
- Utilisé dans : sidebar, page de connexion, première connexion
- Taille sidebar : max 52px de hauteur
- Taille login : max 88px de hauteur

---

## 4. Typographie

| Élément | Style |
|---------|-------|
| Police | **JetBrains Mono** (`JetBrainsMono-Regular.ttf`) — monospace, idéale pour chiffres et tableaux |
| Titres de page | 1.25rem, weight 600, couleur `#000000` |
| Sous-titres | 0.875rem, weight 400, couleur `#374151` |
| KPI — valeur | 1.75rem, weight 600, tabular-nums |
| KPI — label | 0.75rem, uppercase, letter-spacing 0.04em, gris foncé |
| Tableaux | 0.8125rem, tabular-nums pour les montants |
| Montants | Toujours `font-variant-numeric: tabular-nums`, alignés à droite |

---

## 5. Grille et espacements

- **Largeur contenu** : pleine largeur (`ContentWidth.Fluid`)
- **Padding page** : 24px desktop, 16px mobile
- **Gap entre sections** : 24px
- **Cartes KPI** : grille responsive `cols="12" sm="6" lg="3"`
- **Pas d'élévation** excessive : `elevation="0"` + bordure `1px solid #DDE3EA`

---

## 6. Composants standards

### Carte KPI (`KpiStat`)

- Bordure gauche colorée selon le type :
  - `recettes` → vert `#08A04B`
  - `depenses` → rouge `#E53935`
  - `encaisse` → or `#E7C936`
  - `solde` → vert institutionnel
  - `neutral` → gris moyen

### Barre de navigation

- **Layout horizontal** (menu sous le header, pas de sidebar)
- Fond blanc / surface neutre sur toute la barre
- **Item actif** : fond vert `#08A04B`, texte blanc, barre Or en bas
- **Survol** : fond vert léger `rgba(8, 160, 75, 0.1)`
- **Sous-menu actif** : fond vert pâle + barre Or à gauche

### Barre latérale (legacy)

- Non utilisée en v2 — conservée uniquement si bascule verticale via customizer

### Hero dashboard (`.aice-hero`)

- Dégradé vert institutionnel `#08A04B` → `#067A39` → `#045E2C`
- Accent lumineux Or en haut à droite

### Graphiques

- **Chart.js** — palette : vert, gris foncé, or, rouge, gris moyen
- Maximum 4 couleurs par graphique

---

## 7. Navigation

Menu horizontal sous le logo :

```
[Logo DGTCP]                                    [Langue] [Profil]
Tableau de bord ▾ | Données ▾ | Administration ▾
```

- Groupes : Tableau de bord, Données, Administration
- Item actif : vert + accent Or
- Survol : teinte verte légère

---

## 8. Formulaires et actions

- Champs : `variant="outlined"`, `density="compact"`
- Bouton principal : `color="primary"` (vert), `variant="flat"`
- Bouton suppression : `color="error"` (rouge République)
- Bouton secondaire : `variant="outlined"`, `color="secondary"`

---

## 9. Fichiers de configuration

| Fichier | Rôle |
|---------|------|
| `themeConfig.ts` | Logo image, layout, locale FR |
| `plugins/vuetify/theme.ts` | Palette DGTCP + export `dgtcpColors` |
| `resources/styles/styles.scss` | Menu horizontal, logo, typographie |
| `resources/styles/aice/` | Hero, explorer, composants métier |
| `public/images/dgtcp-logo.png` | Logo officiel |
| `resources/styles/fonts/_jetbrains-mono.scss` | Déclaration `@font-face` |
| `public/fonts/JetBrainsMono-Regular.ttf` | Fichier police |
| `constants/typography.ts` | Constante partagée (graphiques) |
| `components/aice/` | Composants réutilisables métier |

---

*Version 2.0 — juin 2026 — Charte officielle DGTCP*
