# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-21

### Added
- Création et gestion d’alertes dans GLPI.
- Configuration des cibles par utilisateur, groupe ou profil.
- Envoi d’emails de notification avec contenu HTML propre et fallback texte.
- Bouton de test d’envoi d’email.
- Exécution des alertes via cron.
- Déclenchement sur champs de date GLPI et sur champs de date issus du plugin Fields.
- Découverte automatique de tous les champs `date`, `datetime` et `timestamp` de la base via `information_schema`.
- Regroupement des champs observés par source dans l’interface.