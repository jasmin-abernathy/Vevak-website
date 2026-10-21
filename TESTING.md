# Parcours de test VeVak

Depuis le 21 octobre 2026, la page `/test/` présentant l’installation directe
de l’APK et les tests guidés a été retirée.

## Parcours actuel : Google Play

- **Inscription publique** : https://vevak.lepotager.org/#devenir-testeur
- **Traitement des candidatures** : `/test/admin-testers.php`, interface privée
  exigeant toujours l’authentification cPanel côté serveur
- **Diffusion de la version destinée au test fermé** : Google Play, après ajout
  du compte du participant dans la Play Console
- **Retours autorisés** : https://vevak.lepotager.org/retours/ (code reçu par e-mail)
- **Détails d’administration et de confidentialité** : [TESTER_REGISTRATION.md](TESTER_REGISTRATION.md)

Le dossier `test/` **ne doit pas être supprimé intégralement** : il héberge
encore le gestionnaire privé des inscriptions. L’absence de page d’accueil dans
ce dossier ne doit jamais conduire à désactiver sa protection serveur ni à
exposer une liste de fichiers.

La release GitHub bêta, quand elle existe, est distincte du recrutement
Google Play. Ne pas demander aux nouveaux participants de télécharger une APK
pour intégrer le test fermé Play Console.
