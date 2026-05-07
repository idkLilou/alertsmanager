<p align="center">
	<img src="./public/pics/alertsmanager.png" alt="alertsmanager" width="400">
</p>

>[!NOTE]
> Plugin GLPI pour créer et gérer des alertes e-mail avec ciblage des destinataires (utilisateur, groupe, profil), configuration des déclencheurs, et personnalisation du contenu des messages.

---

## Sommaire

- [Aperçu](#aperçu)
- [Fonctionnalités](#fonctionnalités)
- [État actuel](#état-actuel)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Architecture du plugin](#architecture-du-plugin)
- [Endpoints AJAX](#endpoints-ajax)
- [Droits et profils](#droits-et-profils)
- [Développement](#développement)
- [Limites connues](#limites-connues)
- [Roadmap](#roadmap)
- [Contribuer](#contribuer)
- [Licence](#licence)
- [Sécurité](#sécurité)
- [Auteur](#auteur)

---

## Aperçu

Alerts Manager permet de :

- créer des alertes internes avec nom, description et statut actif/inactif ;
- configurer un sujet et un contenu d’e-mail (éditeur riche supporté) ;
- cibler les destinataires :
  - utilisateurs
  - groupes
  - profils
- définir des déclencheurs :
  - selon un champ de date observé
  - selon une fréquence planifiée (daily / weekly / monthly côté formulaire).

Le plugin expose une interface GLPI dédiée dans le menu **Outils**.

---

## Fonctionnalités

### Gestion des alertes
- Création, modification, suppression, restauration, purge.
- Liste et recherche des alertes via la recherche GLPI.

### Ciblage des destinataires
- Sélection dynamique des destinataires via AJAX.
- Résolution des destinataires finaux :
  - utilisateurs directs,
  - utilisateurs appartenant aux groupes ciblés,
  - utilisateurs rattachés aux profils ciblés.
- Agrégation des adresses e-mail depuis :
  - `glpi_users.email`
  - `glpi_useremails.email`

### Déclencheurs
- Sélection d’un champ observé (tickets, contrats, licences, assets, projets).
- Paramètres “mois avant” et “jours avant”.
- Paramètre de fréquence.

### UI / UX
- Formulaire Twig intégré au style GLPI.
- Prévisualisation du sujet et contenu e-mail (AJAX).
- Script JS principal pour interactions formulaire et chargements dynamiques.

---

## État actuel

Version plugin : **1.0.0** (*en développement*)

Compatibilité déclarée :
- GLPI min : `11.0.0`
- GLPI max : `11.0.99` (exclusive dans `setup.php`)
- Marketplace metadata : `~11.0.0` dans `plugin.xml`

---

## Prérequis

- PHP >= 8.2
- GLPI 11.x
- Droits GLPI de configuration / droits plugin appropriés

---

## Installation

1. Copier le plugin dans le dossier plugins de GLPI :
   - `glpi/plugins/alertsmanager`

2. Vérifier la structure minimale :
   - `setup.php`
   - `hook.php`
   - `plugin.xml`
   - `logo.png`

3. Aller dans GLPI :
   - **Configuration > Plugins**
   - Installer puis activer **Alerts Manager**

---

## Configuration

### Accès menu
Une fois activé, le plugin ajoute une entrée dans **Outils** (selon droits).

### Droits
Le plugin déclare un droit principal :
- `plugin_alertsmanager_alert`

Actions associées :
- Create
- Read
- Update
- Delete
- Purge

Ces droits sont configurables par profil GLPI.

---

## Utilisation

### Créer une alerte
1. Ouvrir la liste des alertes.
2. Ajouter une nouvelle alerte.
3. Renseigner :
   - nom
   - description
   - actif/inactif
   - sujet e-mail
   - contenu e-mail
4. Choisir le type de cible (User / Group / Profile).
5. Sélectionner les cibles.
6. Définir le déclencheur (champ observé, offset, fréquence).
7. Enregistrer.

### Modifier une alerte
- Ouvrir l’alerte depuis la liste.
- Mettre à jour les paramètres.
- Enregistrer.

---

## Architecture du plugin

### Fichiers principaux
- `setup.php` : init, version, exigences, hooks menu/CSS/JS.
- `hook.php` : installation/désinstallation SQL + déclaration droits profils.
- `plugin.xml` : métadonnées plugin marketplace.
- `logo.png` : logo local utilisé par GLPI.

### Dossier `inc/`
- Modèles / logique métier :
  - alertes
  - triggers
  - cibles (user/group/profile)
  - profils

### Dossier `front/`
- Contrôleurs pages GLPI :
  - listing alertes
  - formulaire alerte
  - écrans complémentaires

### Dossier `ajax/`
- Endpoints de support UI :
  - preview e-mail
  - chargement cibles
  - chargement champs observés
  - endpoints display/hide (base actuellement simple)

### Dossier `public/`
- `css/` : styles du plugin
- `js/` : scripts plugin
- `pics/` : assets image

### Dossier `templates/`
- Templates Twig :
  - formulaire alerte
  - onglet droits profil

---

## Endpoints AJAX

- `ajax/alert_preview.php`
  - rendu de la prévisualisation sujet/contenu e-mail

- `ajax/targets.php`
  - récupération des cibles selon type (User/Group/Profile)
  - support recherche (`q`) et limite (`limit`)

- `ajax/observed_fields.php`
  - récupération des champs de date observables pour déclencheurs

- `ajax/display_alerts.php`
- `ajax/hide_alert.php`
  - endpoints présents, implémentation de base

---

## Droits et profils

Le plugin ajoute un onglet de droits dans les profils GLPI, permettant de gérer les permissions Alerts Manager par profil.

---

## Développement

### Stack
- PHP (classes GLPI `CommonDBTM`, `Profile`, etc.)
- Twig (rendu des formulaires)
- JavaScript (interaction formulaire, AJAX)
- CSS (style plugin)

### Qualité / outillage
- Dépendance dev :
  - `glpi-project/tools`

---

## Limites connues

- Les endpoints `display_alerts` / `hide_alert` sont présents avec une logique minimale.
- La mécanique complète d’envoi automatique planifié n’est pas encore visible comme pipeline finalisé (cron/notification dédiée à compléter selon stratégie projet).
- Certains écrans front annexes sont encore en mode placeholder.

---

## Roadmap

- Finaliser le moteur d’exécution des triggers.
- Mettre en place l’envoi e-mail automatique robuste (planification, retries, logs).
- Ajouter journalisation détaillée des envois.
- Ajouter tests automatisés.
- Stabiliser l’API AJAX (validation et erreurs normalisées).

---

## Contribuer

Consulter :
- `CONTRIBUTING.md`

Process recommandé :
- branch dédiée par ticket
- commits conventionnels
- PR relue + CI verte

---

## Licence

GPL v2+  
Voir :
- `LICENSE`

---

## Sécurité

Pour signaler une vulnérabilité :
- consulter `SECURITY.md`

---

## Auteur

- Lilou DUFAU  
- Repository : https://github.com/idkLilou/alertsmanager
