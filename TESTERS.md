# Testeurs VeVak — Google Play

Le recrutement pour le test fermé Google Play passe désormais uniquement par
[le formulaire public de l’accueil](https://vevak.lepotager.org/#devenir-testeur).

Le formulaire recueille l’adresse du compte Google Play, le modèle de téléphone,
la version Android et la configuration SIM. Il enregistre la candidature dans
le système déjà existant ; il n’ouvre pas automatiquement l’accès au test.

## Inscription et suivi

1. Le candidat remplit le formulaire sur l’accueil.
2. L’administrateur retrouve la demande dans
   `https://vevak.lepotager.org/test/admin-testers.php` (accès protégé par mot de passe et code e-mail).
3. L’administrateur peut envoyer manuellement un accusé de réception depuis l’interface, pour une sélection de candidats ou les 20 premiers non encore informés. Ce message confirme l’enregistrement de la demande sans donner accès immédiatement au test.
4. L’administrateur ajoute l’adresse du compte Google au test fermé dans Google Play Console,
   puis envoie à la personne le lien d’adhésion au test.
5. La personne s’inscrit au programme et installe VeVak depuis Google Play.
6. Pour satisfaire à la condition habituelle de Google Play pour l’accès à la production,
   un minimum de douze testeurs doit rester inscrit au test fermé pendant quatorze jours
   consécutifs. Cela ne signifie pas utiliser l’application tous les jours.
7. Les testeurs peuvent communiquer leurs retours via
   `https://vevak.lepotager.org/retours/` après autorisation de leur accès.

## Ancienne page supprimée

La page d’installation d’APK et sa checklist autrefois publiées sur `/test/`
ont été supprimées. `/test/` devient le point d'accès à l'administration avec
mot de passe et code de connexion par e-mail.

La protection cPanel existante doit être désactivée **manuellement, après déploiement
de la nouvelle authentification**, en conservant les autres protections utiles du serveur.
Voir [TESTER_REGISTRATION.md](TESTER_REGISTRATION.md).

Consulter [TESTER_REGISTRATION.md](TESTER_REGISTRATION.md) pour l’administration,
les exports Play Console et les procédures de suivi.
