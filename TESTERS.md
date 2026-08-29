# Checklist publication espace testeurs

1. Publier le dossier `test/` sur `vevak.lepotager.org`.
2. Activer HTTPS sur le sous-domaine.
3. Dans cPanel → **Confidentialité du répertoire**, protéger le dossier `test/` avec un utilisateur et un mot de passe.
4. Compiler et valider localement l'APK FOSS.
5. Déposer la nouvelle APK dans le dossier Drive VeVak utilisé pour les tests :
   `https://drive.google.com/drive/folders/1CgzsBx0_Lh_TwcjFpFm5KwhYxupj5ZpW`
6. Vérifier les droits de partage du dossier Drive selon le niveau d'accès souhaité, puis vérifier que le bouton de `/test/` ouvre bien ce dossier.
7. Vérifier en navigation privée que `/test/` demande toujours les identifiants côté serveur.
8. Envoyer aux testeurs uniquement l'URL HTTPS de `/test/` et les identifiants par un canal privé.

L'APK n'est plus déposée dans `test/files/` et ne doit jamais être commitée dans le dépôt public.

Détails : voir `TESTING.md`.
