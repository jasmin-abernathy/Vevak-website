# Checklist publication espace testeurs

1. Publier le dossier `test/` sur `vevak.lepotager.org`.
2. Activer HTTPS sur le sous-domaine.
3. Dans cPanel → **Confidentialité du répertoire**, protéger le dossier `test/` avec un utilisateur et un mot de passe.
4. Compiler et valider localement l'APK FOSS.
5. Déposer l'APK **uniquement sur o2switch** sous `test/files/VeVak-foss-test.apk`.
6. Vérifier en navigation privée que `/test/` et l'URL directe de l'APK demandent les identifiants.
7. Envoyer aux testeurs uniquement l'URL HTTPS et les identifiants par un canal privé.

Détails : voir `TESTING.md`.
