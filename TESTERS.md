# Checklist publication espace testeurs

1. Publier le dossier `test/` sur `vevak.lepotager.org`.
2. Activer HTTPS sur le sous-domaine.
3. Dans cPanel → **Confidentialité du répertoire**, protéger le dossier `test/` avec un utilisateur et un mot de passe.
4. Pousser les changements Android sur `main` dans `jasmin-abernathy/vevak`.
5. Attendre que la CI Android réussisse : tests FOSS/Play, builds, lints et vérifications de confidentialité/écoconception.
6. Vérifier que la release GitHub roulante `beta` contient bien `VeVak-foss-test.apk` et `VeVak-foss-test.apk.sha256`.
7. Vérifier que le bouton de `/test/` télécharge bien :
   `https://github.com/jasmin-abernathy/vevak/releases/download/beta/VeVak-foss-test.apk`
8. Vérifier en navigation privée que `/test/` demande toujours les identifiants côté serveur.
9. Envoyer aux testeurs uniquement l'URL HTTPS de `/test/` et les identifiants par un canal privé.

La distribution de l'APK de test est désormais **100 % GitHub** : aucun dépôt manuel sur Google Drive ni dans `test/files/` n'est nécessaire.

La page `/test/` peut rester privée, mais l'APK publiée dans la release GitHub `beta` appartient au dépôt public et est donc publiquement téléchargeable.

Détails : voir `TESTING.md`.
